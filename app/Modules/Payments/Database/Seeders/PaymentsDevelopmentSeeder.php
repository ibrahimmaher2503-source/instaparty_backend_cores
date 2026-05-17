<?php

declare(strict_types=1);

namespace App\Modules\Payments\Database\Seeders;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\IdempotencyKey;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentAttempt;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PaymentsDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050306);

        DB::transaction(function (): void {
            $completedBooking = Booking::query()->where('reference_no', 'BK-DEV-1001')->firstOrFail();
            $submittedBooking = Booking::query()->where('reference_no', 'BK-DEV-1002')->firstOrFail();
            $activeBooking = Booking::query()->where('reference_no', 'BK-DEV-1003')->firstOrFail();
            $customerOne = User::query()->where('email', 'customer.one@instaparty.local')->firstOrFail();
            $customerTwo = User::query()->where('email', 'customer.two@instaparty.local')->firstOrFail();
            $customerThree = User::query()->where('email', 'customer.three@instaparty.local')->firstOrFail();
            $admin = User::query()->where('email', 'admin@instaparty.local')->firstOrFail();

            $capturedPayment = $this->seedPayment(
                booking: $completedBooking,
                user: $customerOne,
                gatewayRef: 'PAY-DEV-1001',
                amountMinor: 400000,
                method: PaymentMethod::Card,
                status: PaymentStatus::Captured,
                capturedAt: '2026-05-16 13:30:00',
            );

            $pendingPayment = $this->seedPayment(
                booking: $submittedBooking,
                user: $customerTwo,
                gatewayRef: 'PAY-DEV-1002',
                amountMinor: 165000,
                method: PaymentMethod::Wallet,
                status: PaymentStatus::Pending,
                capturedAt: null,
            );

            $failedPayment = $this->seedPayment(
                booking: $activeBooking,
                user: $customerThree,
                gatewayRef: 'PAY-DEV-1003',
                amountMinor: 30000,
                method: PaymentMethod::Transfer,
                status: PaymentStatus::Failed,
                capturedAt: null,
                failureCode: 'card_declined',
                failureMessage: ['en' => 'Card declined by issuer.', 'ar' => 'تم رفض البطاقة من جهة الإصدار.'],
            );

            $this->seedAttempt($capturedPayment, 1, 200, ['status' => 'captured', 'provider_ref' => 'CAP-1001']);
            $this->seedAttempt($pendingPayment, 1, 202, null);
            $this->seedAttempt($failedPayment, 1, 402, ['error' => 'card_declined']);

            $this->updateOrCreateFactoryModel(
                Refund::factory()->completed()->make([
                    'public_id' => $this->stablePublicId('refund:REF-DEV-1001'),
                    'payment_id' => $capturedPayment->id,
                    'booking_id' => $completedBooking->id,
                    'amount_minor' => 45000,
                    'amount_currency' => 'EGP',
                    'reason_code' => RefundReasonCode::CustomerRequest,
                    'reason_notes' => ['en' => 'Customer requested a partial refund for the digital item.', 'ar' => 'طلب العميل استردادا جزئيا للعنصر الرقمي.'],
                    'gateway_ref' => 'REF-DEV-1001',
                    'status' => RefundStatus::Completed,
                    'initiated_by' => $admin->id,
                    'processed_at' => '2026-05-22 10:00:00',
                ]),
                ['payment_id' => $capturedPayment->id, 'gateway_ref' => 'REF-DEV-1001'],
            );

            $this->updateOrCreateFactoryModel(
                IdempotencyKey::factory()->make([
                    'key' => 'seed:vendor-withdrawal:sweet-table-studio',
                    'user_id' => $customerTwo->id,
                    'route' => '/api/v1/vendor/withdrawals',
                    'request_hash' => hash('sha256', 'seed:vendor-withdrawal:sweet-table-studio'),
                    'response_status' => 201,
                    'response_body' => ['message' => 'cached development response'],
                    'expires_at' => '2026-05-04 08:00:00',
                    'created_at' => '2026-05-03 08:00:00',
                ]),
                ['key' => 'seed:vendor-withdrawal:sweet-table-studio'],
            );

            $this->seedWebhook('payment.captured', true, ['payment_ref' => 'PAY-DEV-1001'], '2026-05-16 13:30:05');
            $this->seedWebhook('refund.completed', false, ['refund_ref' => 'REF-DEV-1001'], '2026-05-22 10:00:05');
        });
    }

    private function seedPayment(
        Booking $booking,
        User $user,
        string $gatewayRef,
        int $amountMinor,
        PaymentMethod $method,
        PaymentStatus $status,
        ?string $capturedAt,
        ?string $failureCode = null,
        ?array $failureMessage = null,
    ): Payment {
        /** @var Payment $payment */
        $payment = $this->updateOrCreateFactoryModel(
            Payment::factory()->make([
                'public_id' => $this->stablePublicId('payment:'.$gatewayRef),
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'gateway' => 'paymob',
                'gateway_ref' => $gatewayRef,
                'amount_minor' => $amountMinor,
                'amount_currency' => 'EGP',
                'method' => $method,
                'status' => $status->value,
                'captured_at' => $capturedAt,
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
                'metadata' => ['booking_reference' => $booking->reference_no, 'source' => 'development-seeder'],
            ]),
            ['gateway' => 'paymob', 'gateway_ref' => $gatewayRef],
        );

        return $payment;
    }

    private function seedAttempt(Payment $payment, int $attemptNo, int $httpStatus, ?array $response): void
    {
        $this->firstOrCreateFactoryModel(
            PaymentAttempt::factory()->make([
                'payment_id' => $payment->id,
                'attempt_no' => $attemptNo,
                'request_payload' => ['amount_minor' => $payment->amount_minor, 'currency' => $payment->amount_currency],
                'response_payload' => $response,
                'http_status' => $httpStatus,
                'created_at' => $payment->created_at ?? now(),
            ]),
            ['payment_id' => $payment->id, 'attempt_no' => $attemptNo],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function seedWebhook(string $eventType, bool $signatureValid, array $payload, string $createdAt): void
    {
        $this->firstOrCreateFactoryModel(
            GatewayWebhookLog::factory()->make([
                'gateway' => 'paymob',
                'event_type' => $eventType,
                'signature_valid' => $signatureValid,
                'payload' => $payload,
                'processed_at' => $signatureValid ? now() : null,
                'processing_error' => $signatureValid ? null : 'Invalid signature',
                'created_at' => $createdAt,
            ]),
            ['gateway' => 'paymob', 'event_type' => $eventType, 'created_at' => $createdAt],
        );
    }
}
