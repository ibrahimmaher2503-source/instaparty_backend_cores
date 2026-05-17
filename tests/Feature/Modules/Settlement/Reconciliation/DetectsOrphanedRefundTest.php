<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\ReconcileWalletAction;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentReconciliationRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('detects a completed refund row with no ledger_group_id and flags it high severity', function (): void {
    $vp         = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();
    $walletRepo = app(EloquentWalletRepository::class);

    $wallet = $walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        $vp->id,
        'EGP',
    );

    $data    = makeConfirmedBookingWithItem();
    $booking = $data['booking'];

    $payment = \App\Modules\Payments\Domain\Models\Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $booking->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-ORPHAN-' . Str::ulid(),
        'amount_minor'    => 20_000,
        'amount_currency' => 'EGP',
        'method'          => \App\Modules\Payments\Domain\Enums\PaymentMethod::Card,
        'status'          => \App\Modules\Payments\Domain\Enums\PaymentStatus::Captured,
    ]);

    // Insert an orphaned refund: status=completed but no ledger_group_id
    \App\Modules\Payments\Domain\Models\Refund::create([
        'public_id'       => (string) Str::ulid(),
        'payment_id'      => $payment->id,
        'booking_id'      => $booking->id,
        'amount_minor'    => 20_000,
        'amount_currency' => 'EGP',
        'reason_code'     => \App\Modules\Payments\Domain\Enums\RefundReasonCode::CustomerRequest,
        'reason_notes'    => ['en' => 'orphan test', 'ar' => 'اختبار'],
        'status'          => \App\Modules\Payments\Domain\Enums\RefundStatus::Completed,
        'initiated_by'    => $data['customer']->id,
        // ledger_group_id intentionally omitted
    ]);

    $reconRepo = app(EloquentReconciliationRepository::class);
    $run       = $reconRepo->createRun(
        scopeType: 'wallet',
        scopeParams: ['wallet_id' => $wallet->id],
        triggerKind: 'manual',
        triggeredByUserId: null,
        idempotencyKey: null,
        correlationId: (string) Str::ulid(),
    );

    app(ReconcileWalletAction::class)->execute($wallet->id, $run->id);

    $orphanFinding = ReconciliationFinding::query()
        ->where('reconciliation_run_id', $run->id)
        ->where('finding_type', ReconciliationFindingType::OrphanedRefundRow->value)
        ->first();

    expect($orphanFinding)->not()->toBeNull();
    expect($orphanFinding->severity->value)->toBe(ReconciliationFindingSeverity::High->value);
    expect($orphanFinding->resolution)->toBeNull();
})->group('us6');
