<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Models;

use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAuditEntry extends Model
{
    protected $table = 'subscription_audit';

    // Insert-only ledger: no updated_at, no delete
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $casts = [
        'event_type'   => SubscriptionEventType::class,
        'before_state' => 'array',
        'after_state'  => 'array',
        'metadata'     => 'array',
        'created_at'   => 'datetime',
    ];

    public function vendorSubscription(): BelongsTo
    {
        return $this->belongsTo(VendorSubscription::class, 'vendor_subscription_id');
    }
}
