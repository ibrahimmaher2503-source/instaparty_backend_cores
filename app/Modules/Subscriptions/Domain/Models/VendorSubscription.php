<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Models;

use App\Modules\Subscriptions\Database\Factories\VendorSubscriptionFactory;
use App\Modules\Subscriptions\Domain\Enums\BillingCycle;
use App\Modules\Subscriptions\Domain\States\ActiveState;
use App\Modules\Subscriptions\Domain\States\PastDueState;
use App\Modules\Subscriptions\Domain\States\SubscriptionState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

class VendorSubscription extends Model
{
    use HasFactory;
    use HasStates;

    protected $table = 'vendor_subscriptions';

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'subscription_plan_id',
        'billing_cycle',
        'current_period_start',
        'current_period_end',
        'grace_period_ends_at',
        'cancel_at_period_end',
        'is_admin_override',
        'override_reason',
        'override_expires_at',
        'gateway_token_ref',
        'started_at',
        'ended_at',
        'ended_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => SubscriptionState::class,
        'billing_cycle' => BillingCycle::class,
        'cancel_at_period_end' => 'boolean',
        'is_admin_override' => 'boolean',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'override_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected $hidden = ['id'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'vendor_subscription_id');
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(SubscriptionAuditEntry::class, 'vendor_subscription_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereState('status', ActiveState::class);
    }

    public function scopeActiveOrOverride(Builder $query): Builder
    {
        return $query->whereStateIn('status', [ActiveState::class, PastDueState::class]);
    }

    public function scopeAdminOverride(Builder $query): Builder
    {
        return $query->where('is_admin_override', true);
    }

    public function scopeDueForRenewal(Builder $query, \DateTimeInterface $at): Builder
    {
        return $query->whereState('status', ActiveState::class)
            ->where('cancel_at_period_end', false)
            ->where('is_admin_override', false)
            ->where('current_period_end', '<=', $at);
    }

    public function scopeInGrace(Builder $query, \DateTimeInterface $at): Builder
    {
        return $query->whereState('status', PastDueState::class)
            ->where('grace_period_ends_at', '<=', $at);
    }

    public function scopeOverridesExpiredAt(Builder $query, \DateTimeInterface $at): Builder
    {
        return $query->where('is_admin_override', true)
            ->whereState('status', ActiveState::class)
            ->whereNotNull('override_expires_at')
            ->where('override_expires_at', '<=', $at);
    }

    protected static function newFactory(): VendorSubscriptionFactory
    {
        return VendorSubscriptionFactory::new();
    }
}
