<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $this->resolveLocale($request);

        return [
            'public_id' => $this->public_id,
            'is_active' => (bool) $this->is_active,
            'name' => $this->translation('name', $locale),
            'terms' => $this->translation('terms', $locale),
            'points_per_currency_unit' => (float) $this->points_per_currency_unit,
            'points_value' => [
                'minor' => (int) $this->points_value_minor,
                'currency' => (string) $this->points_value_currency,
            ],
            'min_points_to_redeem' => (int) $this->min_points_to_redeem,
            'max_redeem_pct' => (int) $this->max_redeem_pct,
            'points_expire_after_days' => $this->points_expire_after_days,
            'rules' => $this->whenLoaded('activeRules', fn () => $this->activeRules->map(function ($rule) use ($locale) {
                return [
                    'public_id' => $rule->public_id,
                    'rule_kind' => $rule->rule_kind->value,
                    'multiplier' => (float) $rule->multiplier,
                    'label' => $this->translateOn($rule, 'label', $locale),
                    'conditions' => $rule->conditions,
                    'is_active' => (bool) $rule->is_active,
                    'starts_at' => $rule->starts_at?->toISOString(),
                    'ends_at' => $rule->ends_at?->toISOString(),
                ];
            })->all()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function resolveLocale(Request $request): string
    {
        $header = $request->header('Accept-Language', app()->getLocale());

        return str_starts_with((string) $header, 'ar') ? 'ar' : 'en';
    }

    private function translation(string $field, string $locale): ?string
    {
        $value = $this->resource->getTranslation($field, $locale, false);

        return $value !== '' ? $value : ($this->resource->getTranslation($field, 'en', false) ?: null);
    }

    private function translateOn(object $model, string $field, string $locale): ?string
    {
        if (! method_exists($model, 'getTranslation')) {
            return null;
        }
        $value = $model->getTranslation($field, $locale, false);

        return $value !== '' ? $value : ($model->getTranslation($field, 'en', false) ?: null);
    }
}
