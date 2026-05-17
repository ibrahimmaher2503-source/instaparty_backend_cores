<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Resources;

use App\Modules\Shared\Domain\Models\CmsPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CmsPage
 */
class CmsPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'slug' => $this->slug->value,
            'title' => $this->getTranslation('title', $locale),
            'body' => $this->getTranslation('body', $locale),
            'meta_description' => $this->getTranslation('meta_description', $locale) ?: null,
            'blocks' => self::localiseBlocks((array) ($this->blocks ?? []), $locale),
            'published_at' => $this->published_at?->toISOString(),
        ];
    }

    /**
     * Recursively pick the requested locale from any translatable map encountered in the blocks tree.
     */
    private static function localiseBlocks(array $blocks, string $locale): array
    {
        $walk = function (array $node) use (&$walk, $locale): array {
            $out = [];
            foreach ($node as $k => $v) {
                if (is_array($v) && (isset($v['en']) || isset($v['ar']))
                    && count(array_diff(array_keys($v), ['en', 'ar'])) === 0
                ) {
                    $out[$k] = $v[$locale] ?? $v['en'] ?? $v['ar'] ?? null;
                } elseif (is_array($v)) {
                    $out[$k] = $walk($v);
                } else {
                    $out[$k] = $v;
                }
            }

            return $out;
        };

        return $walk($blocks);
    }
}
