<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Models;

use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Subscriptions\Database\Factories\SubscriptionInvoiceFactory;
use App\Modules\Subscriptions\Domain\Enums\BillingCycle;
use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionInvoice extends Model
{
    use HasFactory;

    protected $table = 'subscription_invoices';

    // Append-only: no updated_at
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $casts = [
        'status'        => InvoiceStatus::class,
        'billing_cycle' => BillingCycle::class,
        'amount'        => MoneyCast::class . ':amount',
        'period_start'  => 'datetime',
        'period_end'    => 'datetime',
        'paid_at'       => 'datetime',
        'created_at'    => 'datetime',
    ];

    protected $hidden = ['id'];

    public function vendorSubscription(): BelongsTo
    {
        return $this->belongsTo(VendorSubscription::class, 'vendor_subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'subscription_invoice_id');
    }

    protected static function newFactory(): SubscriptionInvoiceFactory
    {
        return SubscriptionInvoiceFactory::new();
    }
}
