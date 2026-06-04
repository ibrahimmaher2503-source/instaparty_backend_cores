<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * 5.6 — public service themes for the customer theme filter.
 */
class ServiceThemeController
{
    public function index(): JsonResponse
    {
        $locale = app()->getLocale();

        $themes = ServiceTheme::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (ServiceTheme $theme): array => [
                'public_id' => $theme->public_id,
                'code' => $theme->code,
                'name' => $theme->getTranslation('name', $locale, useFallbackLocale: true),
                'icon_url' => $theme->icon_path ?: null,
            ])
            ->values();

        return ApiResponse::success($themes);
    }
}
