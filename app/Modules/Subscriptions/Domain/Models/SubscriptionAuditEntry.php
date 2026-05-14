<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Models;

use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SubscriptionAuditEntry extends Model
{
    protected $table = 'subscription_audit';

    // Insert-only ledger: no updated_at, no delete
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'public_id',
        'vendor_subscription_id',
        'vendor_profile_id',
        'event_type',
        'actor_type',
        'actor_id',
        'before_state',
        'after_state',
        'metadata',
        'reason',
    ];

    protected $casts = [
        'event_type' => SubscriptionEventType::class,
        'before_state' => 'array',
        'after_state' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (! $model->public_id) {
                $model->public_id = (string) Str::ulid();
            }
        });

        static::updating(function () {
            return false;
        });

        static::deleting(function () {
            return false;
        });
    }

    public function vendorSubscription(): BelongsTo
    {
        return $this->belongsTo(VendorSubscription::class, 'vendor_subscription_id');
    }
}
