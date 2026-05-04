<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Database\Factories;

use App\Modules\Subscriptions\Domain\Enums\BillingCycle;
use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionInvoiceFactory extends Factory
{
    protected $model = SubscriptionInvoice::class;

    public function definition(): array
    {
        $start = now()->subDays(15);
        return [
            'public_id'               => (string) Str::ulid(),
            'vendor_subscription_id'  => VendorSubscription::factory(),
            'idempotency_key'         => (string) Str::ulid(),
            'status'                  => InvoiceStatus::Pending->value,
            'billing_cycle'           => BillingCycle::Monthly->value,
            'amount_minor'            => 19900,
            'amount_currency'         => 'EGP',
            'period_start'            => $start,
            'period_end'              => $start->copy()->addMonth(),
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => InvoiceStatus::Paid->value, 'paid_at' => now()]);
    }
}
