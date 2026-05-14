<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Http\Resources;

use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlan
 */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->public_id,
            'plan_code' => $this->plan_code->value,
            'name' => $this->getTranslation('name', $locale),
            'description' => $this->getTranslation('description', $locale),
            'monthly_price' => [
                'minor' => (int) $this->getRawOriginal('monthly_price_minor'),
                'currency' => $this->getRawOriginal('monthly_price_currency'),
            ],
            'yearly_price' => [
                'minor' => (int) $this->getRawOriginal('yearly_price_minor'),
                'currency' => $this->getRawOriginal('yearly_price_currency'),
            ],
            'is_default' => (bool) $this->is_default,
            'is_published' => (bool) $this->is_published,
            'display_order' => (int) $this->display_order,
            'features' => $this->whenLoaded('features', fn () => $this->features
                ->map(fn ($f) => [
                    'feature_key' => $f->feature_key,
                    'value_type' => $f->value_type,
                    'value' => match ($f->value_type) {
                        'int' => (int) $f->value_int,
                        'bool' => (bool) $f->value_bool,
                        'string' => (string) $f->value_string,
                        default => null,
                    },
                    'label' => $f->getTranslation('label', $locale),
                ])
                ->all(),
            ),
        ];
    }
}
