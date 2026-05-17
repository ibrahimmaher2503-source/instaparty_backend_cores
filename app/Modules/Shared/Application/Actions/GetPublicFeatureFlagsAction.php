<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

class GetPublicFeatureFlagsAction
{
    public const CACHE_KEY = 'theme:feature_flags:public';
    public const CACHE_TTL_SECONDS = 60;

    /**
     * Returns ONLY flags whose key matches `frontend.*` namespace.
     * Never expose internal flags (e.g. financial_ledger_hardening_v2).
     */
    public function execute(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            return FeatureFlag::query()
                ->where('key', 'like', 'frontend.%')
                ->get(['key', 'is_enabled', 'rollout_pct'])
                ->map(fn (FeatureFlag $flag): array => [
                    'key' => $flag->key,
                    'is_enabled' => (bool) $flag->is_enabled,
                    'rollout_pct' => (int) ($flag->rollout_pct ?? 100),
                ])
                ->all();
        });
    }
}
