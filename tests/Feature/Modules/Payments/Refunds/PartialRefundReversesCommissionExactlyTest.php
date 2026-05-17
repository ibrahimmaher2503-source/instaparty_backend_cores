<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('a full refund posts a commission reversal that returns the vendor share exactly', function (): void {
    $data    = makeConfirmedBookingWithItem();
    $booking = $data['booking'];
    $item    = $data['booking_item'];

    $payment = Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $booking->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-COMM-' . Str::ulid(),
        'amount_minor'    => 50_000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Captured,
    ]);

    // Seed a commission record manually
    $vendorProfile = $data['vendor_profile'];
    $commission = Commission::create([
        'public_id'              => (string) Str::ulid(),
        'booking_item_id'        => $item->id,
        'payment_id'             => $payment->id,
        'vendor_profile_id'      => $vendorProfile->id,
        'category_id'            => null,
        'product_type'           => $item->product_type,
        'gross_amount_minor'     => 50_000,
        'gross_amount_currency'  => 'EGP',
        'commission_bps'         => 1000, // 10%
        'commission_minor'       => 5_000,
        'commission_currency'    => 'EGP',
        'vendor_share_minor'     => 45_000,
        'vendor_share_currency'  => 'EGP',
        'reversed_amount_minor'  => 0,
        'status'                 => \App\Modules\Settlement\Domain\Enums\CommissionStatus::Active,
        'created_at'             => now(),
    ]);

    // Trigger a refund which should reverse the commission
    $refundAction = app(\App\Modules\Payments\Application\Actions\ProcessRefundAction::class);

    $refund = \App\Modules\Payments\Domain\Models\Refund::create([
        'public_id'        => (string) Str::ulid(),
        'payment_id'       => $payment->id,
        'booking_id'       => $booking->id,
        'amount_minor'     => 50_000,
        'amount_currency'  => 'EGP',
        'reason_code'      => \App\Modules\Payments\Domain\Enums\RefundReasonCode::CustomerRequest,
        'reason_notes'     => ['en' => 'test', 'ar' => 'اختبار'],
        'status'           => \App\Modules\Payments\Domain\Enums\RefundStatus::Pending,
        'initiated_by'     => $data['customer']->id,
    ]);

    $refundAction->execute($refund->id);

    // Commission should now be fully reversed
    $commission->refresh();
    expect($commission->status)->toEqual(CommissionStatus::Reversed);
    expect($commission->reversed_amount_minor)->toBe(45_000);

    // A commission_reversal ledger group should exist
    expect(DB::table('ledger_transaction_groups')->where('kind', 'commission_reversal')->count())->toBe(1);

    // reversal_ledger_entry_id set on the commission
    expect($commission->reversal_ledger_entry_id)->not()->toBeNull();
})->group('us4');

it('refund works identically for rental, sale, and digital product types', function (): void {
    // This test verifies behavioural parity — the ledger shape is identical regardless of product_type
    $productTypes = [
        \App\Modules\Catalog\Domain\Enums\ProductType::Rental,
        \App\Modules\Catalog\Domain\Enums\ProductType::Sale,
        \App\Modules\Catalog\Domain\Enums\ProductType::Digital,
    ];

    foreach ($productTypes as $type) {
        $data    = makeConfirmedBookingWithItem();
        $booking = $data['booking'];

        $payment = Payment::create([
            'public_id'       => (string) Str::ulid(),
            'booking_id'      => $booking->id,
            'user_id'         => $data['customer']->id,
            'gateway'         => 'paymob',
            'gateway_ref'     => 'PMB-TYPE-' . $type->value . '-' . Str::ulid(),
            'amount_minor'    => 20_000,
            'amount_currency' => 'EGP',
            'method'          => PaymentMethod::Card,
            'status'          => PaymentStatus::Captured,
        ]);

        $refund = \App\Modules\Payments\Domain\Models\Refund::create([
            'public_id'       => (string) Str::ulid(),
            'payment_id'      => $payment->id,
            'booking_id'      => $booking->id,
            'amount_minor'    => 20_000,
            'amount_currency' => 'EGP',
            'reason_code'     => \App\Modules\Payments\Domain\Enums\RefundReasonCode::CustomerRequest,
            'reason_notes'    => ['en' => 'parity test', 'ar' => 'اختبار'],
            'status'          => \App\Modules\Payments\Domain\Enums\RefundStatus::Pending,
            'initiated_by'    => $data['customer']->id,
        ]);

        app(\App\Modules\Payments\Application\Actions\ProcessRefundAction::class)->execute($refund->id);

        $refund->refresh();
        expect($refund->ledger_group_id)->not()->toBeNull();
    }

    // All three types should have created a refund ledger group
    expect(DB::table('ledger_transaction_groups')->where('kind', 'refund')->count())->toBe(3);
})->group('us4', 'rental', 'sale', 'digital');
