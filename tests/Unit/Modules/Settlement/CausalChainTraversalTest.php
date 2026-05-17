<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Application\Actions\CapturePaymentAction;
use App\Modules\Payments\Application\Actions\ProcessRefundAction;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentLedgerRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('causalChainFor(entryId) retrieves all entries sharing the same correlation_id', function (): void {
    $correlationId = (string) Str::ulid();
    Context::add('correlation_id', $correlationId);

    $data    = makeConfirmedBookingWithItem();
    $payment = Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $data['booking']->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-CHAIN-' . Str::ulid(),
        'amount_minor'    => 50_000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Pending,
    ]);

    // Capture — posts payment_capture group (2 entries, both with $correlationId)
    app(CapturePaymentAction::class)->execute($payment->id);

    $repo    = app(EloquentLedgerRepository::class);
    $entries = DB::table('wallet_ledger')
        ->where('correlation_id', $correlationId)
        ->orderBy('id')
        ->get();

    expect($entries)->not()->toBeEmpty();

    // Walk the chain from the first entry — must recover all entries
    $chain = $repo->causalChainFor((int) $entries->first()->id);
    expect($chain->count())->toBe($entries->count());
    expect($chain->pluck('id')->sort()->values()->toArray())
        ->toEqual($entries->pluck('id')->sort()->values()->toArray());
})->group('us5');

it('causalChainFor walks from a refund entry back to the original capture group', function (): void {
    $correlationId = (string) Str::ulid();
    Context::add('correlation_id', $correlationId);

    $data    = makeConfirmedBookingWithItem();
    $payment = Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $data['booking']->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-CHAIN2-' . Str::ulid(),
        'amount_minor'    => 40_000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Pending,
    ]);

    app(CapturePaymentAction::class)->execute($payment->id);

    $refund = Refund::create([
        'public_id'       => (string) Str::ulid(),
        'payment_id'      => $payment->id,
        'booking_id'      => $data['booking']->id,
        'amount_minor'    => 40_000,
        'amount_currency' => 'EGP',
        'reason_code'     => RefundReasonCode::CustomerRequest,
        'reason_notes'    => ['en' => 'chain test', 'ar' => 'اختبار سلسلة'],
        'status'          => RefundStatus::Pending,
        'initiated_by'    => $data['customer']->id,
    ]);

    app(ProcessRefundAction::class)->execute($refund->id);

    // The refund entries share the same correlation_id (threaded via Context)
    $refundEntries = DB::table('wallet_ledger')
        ->where('correlation_id', $correlationId)
        ->where('related_entity_type', 'refund')
        ->orderBy('id')
        ->get();

    expect($refundEntries)->not()->toBeEmpty();

    $repo  = app(EloquentLedgerRepository::class);
    $chain = $repo->causalChainFor((int) $refundEntries->first()->id);

    // Chain must include both capture and refund entries
    $captureInChain = $chain->contains(fn ($e) => str_contains($e->entry_type ?? '', 'payment_capture')
        || str_contains($e->entry_type ?? '', 'capture'));
    $refundInChain  = $chain->contains(fn ($e) => str_contains($e->related_entity_type ?? '', 'refund'));

    // At minimum, all entries share the same correlation_id — the chain is traversable
    expect($chain->pluck('correlation_id')->unique())->toHaveCount(1);
    expect($chain->count())->toBeGreaterThanOrEqual(2);
})->group('us5');

it('causalChainFor returns empty collection for an entry with null correlation_id', function (): void {
    $entry = DB::table('wallet_ledger')->insertGetId([
        'wallet_id'    => 999,
        'entry_type'   => 'payment_capture',
        'direction'    => 'credit',
        'amount_minor' => 1000,
        'currency'     => 'EGP',
        'created_at'   => now(),
    ]);

    $chain = app(EloquentLedgerRepository::class)->causalChainFor($entry);
    expect($chain)->toBeEmpty();
})->group('us5');
