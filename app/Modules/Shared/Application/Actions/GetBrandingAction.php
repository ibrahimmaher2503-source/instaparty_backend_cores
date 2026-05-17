<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Models\BrandingSetting;
use Illuminate\Support\Facades\Cache;

class GetBrandingAction
{
    public const CACHE_KEY = 'theme:branding';
    public const CACHE_TTL_SECONDS = 300;

    public function execute(string $locale): array
    {
        $cacheKey = self::CACHE_KEY.':'.$locale;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($locale): array {
            $branding = BrandingSetting::current();

            return [
                'public_id' => $branding->public_id,
                'site_name' => $branding->getTranslation('site_name', $locale, useFallbackLocale: true),
                'tagline' => $branding->getTranslation('tagline', $locale, useFallbackLocale: true) ?: null,
                'address_line' => $branding->getTranslation('address_line', $locale, useFallbackLocale: true) ?: null,
                'support_email' => $branding->support_email,
                'support_phone' => $branding->support_phone,
                'whatsapp_number' => $branding->whatsapp_number,
                'social' => (array) ($branding->social ?? []),
                'assets' => [
                    'logo_light' => $branding->getFirstMediaUrl('logo_light') ?: null,
                    'logo_dark' => $branding->getFirstMediaUrl('logo_dark') ?: null,
                    'favicon' => $branding->getFirstMediaUrl('favicon') ?: null,
                    'og_image' => $branding->getFirstMediaUrl('og_image') ?: null,
                    'app_store_badge' => $branding->getFirstMediaUrl('app_store_badge') ?: null,
                    'play_store_badge' => $branding->getFirstMediaUrl('play_store_badge') ?: null,
                ],
                'updated_at' => $branding->updated_at?->toISOString(),
            ];
        });
    }
}
