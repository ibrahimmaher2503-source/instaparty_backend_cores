<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\PostLedgerTransactionAction;
use App\Modules\Settlement\Application\Actions\SettlementRunAction;
use App\Modules\Settlement\Application\DTOs\LedgerTransactionResult;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('partial batch resumes cleanly with no duplicate settle entries', function (): void {
    $walletRepo = app(EloquentWalletRepository::class);
    $ownerType  = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    // Create 10 approved withdrawals — one per vendor; track their IDs
    $withdrawalIds = collect(range(1, 10))->map(function () use ($walletRepo, $ownerType): int {
        $vp     = VendorProfile::factory()->create();
        $wallet = $walletRepo->firstOrCreate($ownerType, $vp->id, 'EGP');

        $entryId = DB::table('wallet_ledger')->insertGetId([
            'wallet_id'             => $wallet->id,
            'direction'             => 'debit',
            'amount_minor'          => 50_000,
            'running_balance_minor' => 0,
            'currency'              => 'EGP',
            'entry_type'            => 'withdrawal_reserve',
            'posted_at'             => now(),
            'created_at'            => now(),
        ]);

        $wd = Withdrawal::factory()->approved()->create([
            'vendor_profile_id'         => $vp->id,
            'requested_amount_minor'    => 50_000,
            'requested_amount_currency' => 'EGP',
            'reserved_ledger_entry_id'  => $entryId,
        ]);

        return $wd->id;
    })->toArray();

    // ── Run 1: mock LedgerWriter to simulate crash after the 5th settle ──────
    // Calls 1-5 succeed; calls 6-10 throw (simulating a hard stop / gateway failure).
    $callCount  = 0;
    $realWriter = app(PostLedgerTransactionAction::class);

    $mock = Mockery::mock(LedgerWriter::class);
    $mock->shouldReceive('post')
        ->andReturnUsing(function ($input) use (&$callCount, $realWriter): LedgerTransactionResult {
            $callCount++;
            if ($callCount > 5) {
                throw new RuntimeException("Simulated payout failure on call #{$callCount}");
            }

            return $realWriter->post($input);
        });

    app()->instance(LedgerWriter::class, $mock);

    $run1 = app(SettlementRunAction::class)->execute();

    Mockery::close();
    app()->forgetInstance(LedgerWriter::class);

    expect($run1->settled)->toBe(5);
    expect($run1->failed)->toBe(5);
    expect(Withdrawal::whereIn('id', $withdrawalIds)->where('status', 'paid')->count())->toBe(5);
    expect(Withdrawal::whereIn('id', $withdrawalIds)->where('status', 'approved')->count())->toBe(5);

    // ── Run 2: no mock — settle the remaining 5 ──────────────────────────────
    $run2 = app(SettlementRunAction::class)->execute();

    expect($run2->settled)->toBe(5);
    expect($run2->failed)->toBe(0);
    expect(Withdrawal::whereIn('id', $withdrawalIds)->where('status', 'paid')->count())->toBe(10);

    // ── Assert exactly one settle group per withdrawal (no duplicates) ────────
    Withdrawal::whereIn('id', $withdrawalIds)->each(function (Withdrawal $wd): void {
        $settleCount = DB::table('ledger_transaction_groups')
            ->where('idempotency_key', "wd_settle_batch:{$wd->id}")
            ->count();

        expect($settleCount)->toBe(1, "Withdrawal {$wd->id} should have exactly one settle group");
    });
})->group('us7');
