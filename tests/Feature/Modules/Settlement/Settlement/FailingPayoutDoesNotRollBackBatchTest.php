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

it('a single failing payout does not roll back the rest of the batch', function (): void {
    $walletRepo = app(EloquentWalletRepository::class);
    $ownerType  = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    // Create 5 approved withdrawals
    $withdrawalIds = collect(range(1, 5))->map(function (int $i) use ($walletRepo, $ownerType): int {
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

    // Mock: 3rd call (withdrawal index 2) throws — simulating a gateway error
    $callCount  = 0;
    $failingIdx = 3;
    $realWriter = app(PostLedgerTransactionAction::class);

    $mock = Mockery::mock(LedgerWriter::class);
    $mock->shouldReceive('post')
        ->andReturnUsing(function ($input) use (&$callCount, $failingIdx, $realWriter): LedgerTransactionResult {
            $callCount++;
            if ($callCount === $failingIdx) {
                throw new RuntimeException("Simulated gateway error on withdrawal #{$callCount}");
            }

            return $realWriter->post($input);
        });

    app()->instance(LedgerWriter::class, $mock);

    $result = app(SettlementRunAction::class)->execute();

    Mockery::close();
    app()->forgetInstance(LedgerWriter::class);

    // 4 paid, 1 failed (the 3rd)
    expect($result->settled)->toBe(4);
    expect($result->failed)->toBe(1);
    expect($result->errors)->toHaveCount(1);

    expect(Withdrawal::whereIn('id', $withdrawalIds)->where('status', 'paid')->count())->toBe(4);
    expect(Withdrawal::whereIn('id', $withdrawalIds)->where('status', 'approved')->count())->toBe(1);

    // The settled withdrawals have settled_ledger_entry_id set
    Withdrawal::whereIn('id', $withdrawalIds)->where('status', 'paid')->each(function (Withdrawal $wd): void {
        expect($wd->settled_ledger_entry_id)->not()->toBeNull();
    });
})->group('us7');
