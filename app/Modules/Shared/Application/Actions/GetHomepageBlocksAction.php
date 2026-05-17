<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Enums\HomeBlockType;
use App\Modules\Shared\Domain\Models\HomeBlock;
use Illuminate\Support\Facades\Cache;

class GetHomepageBlocksAction
{
    public const CACHE_TTL_SECONDS = 60;

    public function execute(string $locale): array
    {
        return Cache::remember('theme:homepage:'.$locale, self::CACHE_TTL_SECONDS, function () use ($locale): array {
            $blocks = HomeBlock::query()
                ->visible()
                ->inWindow()
                ->orderBy('position')
                ->get();

            return $blocks->map(fn (HomeBlock $b): array => [
                'public_id' => $b->public_id,
                'block_type' => $b->block_type->value,
                'name' => $b->name,
                'position' => $b->position,
                'payload' => $this->localisePayload($b->block_type, (array) $b->payload, $locale),
            ])->all();
        });
    }

    private function localisePayload(HomeBlockType $type, array $payload, string $locale): array
    {
        $pickLocale = function (mixed $value) use ($locale): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (isset($value['en']) || isset($value['ar'])) {
                return $value[$locale] ?? $value['en'] ?? $value['ar'] ?? null;
            }

            return $value;
        };

        $deepMap = function (array $node) use (&$deepMap, $pickLocale): array {
            $out = [];
            foreach ($node as $k => $v) {
                if (is_array($v) && (isset($v['en']) || isset($v['ar']))) {
                    $out[$k] = $pickLocale($v);
                } elseif (is_array($v)) {
                    $out[$k] = $deepMap($v);
                } else {
                    $out[$k] = $v;
                }
            }

            return $out;
        };

        return $deepMap($payload);
    }
}
