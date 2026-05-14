<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class PlanFeature extends Model
{
    use HasTranslations;

    protected $table = 'plan_features';

    protected $fillable = [
        'subscription_plan_id',
        'feature_key',
        'value_type',
        'value_int',
        'value_bool',
        'value_string',
        'label',
    ];

    protected $translatable = ['label'];

    protected $casts = [
        'value_bool' => 'boolean',
        'value_int' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('feature_key', $key);
    }

    public function typedValue(): int|bool|string|null
    {
        return match ($this->value_type) {
            'int' => $this->value_int,
            'bool' => $this->value_bool,
            'string' => $this->value_string,
            default => null,
        };
    }
}
