<?php

declare(strict_types=1);

use App\Modules\Payments\Application\Actions\ProcessRefundAction;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('duplicate gateway refund callback creates exactly one refund ledger group', function (): void {
    $data    = makeConfirmedBookingWithItem();
    $booking = $data['booking'];

    $payment = Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $booking->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-DUPREF-' . Str::ulid(),
        'amount_minor'    => 45_000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Captured,
    ]);

    $refund = Refund::create([
        'public_id'       => (string) Str::ulid(),
        'payment_id'      => $payment->id,
        'booking_id'      => $booking->id,
        'amount_minor'    => 45_000,
        'amount_currency' => 'EGP',
        'reason_code'     => RefundReasonCode::CustomerRequest,
        'reason_notes'    => ['en' => 'duplicate callback test', 'ar' => 'اختبار التكرار'],
        'status'          => RefundStatus::Pending,
        'initiated_by'    => $data['customer']->id,
    ]);

    $action = app(ProcessRefundAction::class);

    // First gateway callback — processes and writes the ledger group
    $action->execute($refund->id);

    $groupsAfterFirst = DB::table('ledger_transaction_groups')->where('kind', 'refund')->count();
    expect($groupsAfterFirst)->toBe(1);

    // Second gateway callback (duplicate) — must not create a new ledger group
    $action->execute($refund->id);

    $groupsAfterSecond = DB::table('ledger_transaction_groups')->where('kind', 'refund')->count();
    expect($groupsAfterSecond)->toBe(1);

    // Refund row must be in completed state
    $refund->refresh();
    expect($refund->status)->toEqual(RefundStatus::Completed);
    expect($refund->ledger_group_id)->not()->toBeNull();
})->group('idempotency', 'us4');

it('second duplicate callback does not produce extra wallet_ledger rows', function (): void {
    $data    = makeConfirmedBookingWithItem();
    $booking = $data['booking'];

    $payment = Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $booking->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-DUPLEDGER-' . Str::ulid(),
        'amount_minor'    => 30_000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Captured,
    ]);

    $refund = Refund::create([
        'public_id'       => (string) Str::ulid(),
        'payment_id'      => $payment->id,
        'booking_id'      => $booking->id,
        'amount_minor'    => 30_000,
        'amount_currency' => 'EGP',
        'reason_code'     => RefundReasonCode::CustomerRequest,
        'reason_notes'    => ['en' => 'ledger dup test', 'ar' => 'اختبار'],
        'status'          => RefundStatus::Pending,
        'initiated_by'    => $data['customer']->id,
    ]);

    $action = app(ProcessRefundAction::class);

    $action->execute($refund->id);
    $ledgerRowsAfterFirst = DB::table('wallet_ledger')->count();

    $action->execute($refund->id);
    $ledgerRowsAfterSecond = DB::table('wallet_ledger')->count();

    expect($ledgerRowsAfterSecond)->toBe($ledgerRowsAfterFirst);
})->group('idempotency', 'us4');
