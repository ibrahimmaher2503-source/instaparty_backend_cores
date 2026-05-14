<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Models;

use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Subscriptions\Database\Factories\SubscriptionPlanFactory;
use App\Modules\Subscriptions\Domain\Enums\PlanCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class SubscriptionPlan extends Model
{
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'public_id',
        'plan_code',
        'name',
        'description',
        'monthly_price_minor',
        'monthly_price_currency',
        'yearly_price_minor',
        'yearly_price_currency',
        'is_default',
        'is_published',
        'display_order',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $translatable = ['name', 'description'];

    protected $casts = [
        'plan_code' => PlanCode::class,
        'monthly_price' => MoneyCast::class.':monthly_price',
        'yearly_price' => MoneyCast::class.':yearly_price',
        'is_default' => 'boolean',
        'is_published' => 'boolean',
    ];

    protected $hidden = ['id'];

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'subscription_plan_id');
    }

    public function vendorSubscriptions(): HasMany
    {
        return $this->hasMany(VendorSubscription::class, 'subscription_plan_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order');
    }

    protected static function newFactory(): SubscriptionPlanFactory
    {
        return SubscriptionPlanFactory::new();
    }
}
