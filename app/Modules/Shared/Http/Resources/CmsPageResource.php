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
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
