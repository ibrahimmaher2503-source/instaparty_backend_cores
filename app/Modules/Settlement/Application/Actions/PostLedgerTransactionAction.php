<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\LedgerTransactionResult;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\WalletLocker;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Events\LedgerTransactionPosted;
use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Settlement\Domain\Exceptions\UnbalancedTransactionException;
use App\Modules\Settlement\Domain\Exceptions\WalletCurrencyMismatchException;
use App\Modules\Settlement\Domain\Models\LedgerTransactionGroup;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentLedgerRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostLedgerTransactionAction implements LedgerWriter
{
    public function __construct(
        private readonly EloquentWalletRepository $walletRepo,
        private readonly EloquentLedgerRepository $ledgerRepo,
        private readonly WalletLocker $locker,
        private readonly ProjectWalletBalanceAction $projector,
    ) {}

    public function post(PostLedgerTransactionInput $input): LedgerTransactionResult
    {
        // (a) Idempotency check — outside the transaction so reads don't hold locks
        $existingGroup = $this->ledgerRepo->findGroupByIdempotencyKey($input->idempotencyKey);

        if ($existingGroup !== null) {
            $this->assertPayloadMatch($existingGroup, $input);

            return new LedgerTransactionResult(
                groupId: $existingGroup->id,
                groupPublicId: $existingGroup->public_id,
                wasIdempotentReplay: true,
                newBalances: [],
            );
        }

        // (b) Resolve wallets for all entries
        $wallets     = $this->resolveWallets($input);
        $walletIds   = array_unique(array_map(fn (Wallet $w) => $w->id, array_values($wallets)));
        $newBalances = [];

        // (c) Verify currency invariant before acquiring locks
        $this->assertCurrencyMatch($input, $wallets);

        // (d) Verify balanced invariant
        $this->assertBalanced($input->entries);

        // (e) Acquire Redis locks in ascending order (deadlock prevention)
        $handles = $this->locker->tryAcquireMany($walletIds, ttlSeconds: 30, waitSeconds: 5);

        if ($handles === null) {
            throw new \App\Modules\Settlement\Domain\Exceptions\LockAcquisitionTimeoutException(
                'Could not acquire wallet locks within 5 seconds.'
            );
        }

        try {
            $group = DB::transaction(function () use ($input, $wallets, &$newBalances): LedgerTransactionGroup {
                // (f) Insert the transaction group
                $group = LedgerTransactionGroup::create([
                    'public_id'            => Str::ulid()->toBase32(),
                    'kind'                 => $input->kind->value,
                    'currency'             => $input->currency,
                    'idempotency_key'      => $input->idempotencyKey,
                    'correlation_id'       => $input->correlationId,
                    'causation_id'         => $input->causationId,
                    'initiator_type'       => $input->initiatorType,
                    'initiated_by_user_id' => $input->initiatedByUserId,
                    'metadata'             => $input->metadata,
                    'posted_at'            => now(),
                    'created_at'           => now(),
                ]);

                // (g) Insert ledger entries
                foreach ($input->entries as $entry) {
                    $wallet = $wallets[$entry->walletOwnerType . ':' . $entry->walletOwnerId];

                    DB::table('wallet_ledger')->insert([
                        'wallet_id'            => $wallet->id,
                        'transaction_group_id' => $group->id,
                        'direction'            => $entry->direction->value,
                        'amount_minor'         => $entry->amountMinor,
                        'currency'             => $input->currency,
                        'entry_type'           => $entry->entryType->value,
                        'counter_account_type' => $entry->counterAccountType,
                        'counter_account_id'   => $entry->counterAccountId,
                        'correlation_id'       => $input->correlationId,
                        'causation_id'         => $input->causationId,
                        'idempotency_key'      => $input->idempotencyKey,
                        'description_key'      => $entry->descriptionKey,
                        'description_params'   => $entry->descriptionParams !== null
                            ? json_encode($entry->descriptionParams)
                            : null,
                        'related_entity_type'  => $entry->relatedEntityType,
                        'related_entity_id'    => $entry->relatedEntityId,
                        'posted_at'            => now(),
                        'created_at'           => now(),
                    ]);
                }

                // (h) Update projection cache for each affected wallet
                $affectedWalletIds = array_unique(array_map(
                    fn ($wallet) => $wallet->id,
                    array_values($wallets)
                ));

                foreach ($affectedWalletIds as $walletId) {
                    $projection = $this->projector->recompute($walletId);
                    $this->walletRepo->applyProjection(
                        $walletId,
                        $projection->balanceMinor,
                        $projection->pendingWithdrawalMinor,
                        $projection->lastLedgerEntryId ?? 0,
                    );
                    $newBalances[$walletId] = $projection->balanceMinor;
                }

                DB::afterCommit(function () use ($group, $input, $affectedWalletIds): void {
                    event(new LedgerTransactionPosted(
                        groupId: $group->id,
                        groupPublicId: $group->public_id,
                        kind: $input->kind,
                        correlationId: $input->correlationId,
                        affectedWalletIds: $affectedWalletIds,
                    ));
                });

                return $group;
            });
        } finally {
            foreach ($handles as $handle) {
                $handle->release();
            }
        }

        return new LedgerTransactionResult(
            groupId: $group->id,
            groupPublicId: $group->public_id,
            wasIdempotentReplay: false,
            newBalances: $newBalances,
        );
    }

    /** @return array<string, Wallet>  keyed by "ownerType:ownerId" */
    private function resolveWallets(PostLedgerTransactionInput $input): array
    {
        $wallets = [];

        foreach ($input->entries as $entry) {
            $key = $entry->walletOwnerType . ':' . $entry->walletOwnerId;

            if (! isset($wallets[$key])) {
                // Find any existing wallet first (currency-agnostic), so assertCurrencyMatch
                // can catch mismatches rather than silently creating a new wallet.
                $wallet = $this->walletRepo->findByOwnerAny(
                    $entry->walletOwnerType,
                    $entry->walletOwnerId,
                );

                if ($wallet === null) {
                    $wallet = $this->walletRepo->firstOrCreate(
                        $entry->walletOwnerType,
                        $entry->walletOwnerId,
                        $input->currency,
                    );
                }

                $wallets[$key] = $wallet;
            }
        }

        return $wallets;
    }

    /** @param  array<string, Wallet>  $wallets */
    private function assertCurrencyMatch(PostLedgerTransactionInput $input, array $wallets): void
    {
        foreach ($wallets as $wallet) {
            if ($wallet->currency !== $input->currency) {
                throw new WalletCurrencyMismatchException(
                    "Wallet {$wallet->id} has currency {$wallet->currency}, but transaction is {$input->currency}."
                );
            }
        }
    }

    /** @param  list<LedgerEntryInput>  $entries */
    private function assertBalanced(array $entries): void
    {
        $totalDebits  = 0;
        $totalCredits = 0;

        foreach ($entries as $entry) {
            if ($entry->direction === LedgerDirection::Debit) {
                $totalDebits += $entry->amountMinor;
            } else {
                $totalCredits += $entry->amountMinor;
            }
        }

        if ($totalDebits !== $totalCredits) {
            throw new UnbalancedTransactionException($totalDebits, $totalCredits);
        }
    }

    private function assertPayloadMatch(LedgerTransactionGroup $existing, PostLedgerTransactionInput $input): void
    {
        // Detect gross misuse: same idempotency key used for a different transaction kind.
        // Full payload hashing requires a payload_hash column; for now we guard kind + currency.
        if ($existing->kind->value !== $input->kind->value || $existing->currency !== $input->currency) {
            throw new DuplicateIdempotencyKeyWithDifferentPayloadException(
                "Idempotency key '{$input->idempotencyKey}' was already used for kind={$existing->kind->value}/{$existing->currency}."
            );
        }
    }
}
