<?php

declare(strict_types=1);

use App\Modules\Settlement\Domain\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('ledger:backfill populates direction and posted_at on legacy rows', function (): void {
    $wallet    = Wallet::factory()->create();
    $createdAt = now()->subDays(7)->format('Y-m-d H:i:s');

    // Insert 3 legacy rows without direction or posted_at to simulate pre-Phase-4.9 data.
    DB::table('wallet_ledger')->insert([
        [
            'wallet_id'             => $wallet->id,
            'entry_type'            => 'commission_credit',
            'amount_minor'          => 10_000,
            'running_balance_minor' => 0,
            'currency'              => 'EGP',
            'created_at'            => $createdAt,
        ],
        [
            'wallet_id'             => $wallet->id,
            'entry_type'            => 'withdrawal_debit',
            'amount_minor'          => 5_000,
            'running_balance_minor' => 0,
            'currency'              => 'EGP',
            'created_at'            => $createdAt,
        ],
        [
            'wallet_id'             => $wallet->id,
            'entry_type'            => 'withdrawal_reject_release',
            'amount_minor'          => 5_000,
            'running_balance_minor' => 0,
            'currency'              => 'EGP',
            'created_at'            => $createdAt,
        ],
    ]);

    expect(
        DB::table('wallet_ledger')->where('wallet_id', $wallet->id)->whereNull('direction')->count()
    )->toBe(3);

    $exitCode = Artisan::call('ledger:backfill');
    expect($exitCode)->toBe(0);

    // All rows for this wallet now have direction and posted_at populated.
    $rows = DB::table('wallet_ledger')->where('wallet_id', $wallet->id)->get();

    foreach ($rows as $row) {
        expect($row->direction)->not()->toBeNull("Row id={$row->id} entry_type={$row->entry_type} is missing direction");
        expect($row->posted_at)->not()->toBeNull("Row id={$row->id} entry_type={$row->entry_type} is missing posted_at");
    }

    // Verify correct direction per entry_type.
    $creditRow = DB::table('wallet_ledger')
        ->where('wallet_id', $wallet->id)
        ->where('entry_type', 'commission_credit')
        ->first();
    expect($creditRow->direction)->toBe('credit');

    $debitRow = DB::table('wallet_ledger')
        ->where('wallet_id', $wallet->id)
        ->where('entry_type', 'withdrawal_debit')
        ->first();
    expect($debitRow->direction)->toBe('debit');

    $releaseRow = DB::table('wallet_ledger')
        ->where('wallet_id', $wallet->id)
        ->where('entry_type', 'withdrawal_reject_release')
        ->first();
    expect($releaseRow->direction)->toBe('credit');

    // No null-direction rows remain globally after the backfill.
    expect(DB::table('wallet_ledger')->whereNull('direction')->count())->toBe(0);
})->group('us8');
