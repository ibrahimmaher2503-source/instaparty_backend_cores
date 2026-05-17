<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use App\Modules\Shared\Domain\Models\BrandingSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SaveBrandingAction
{
    public function execute(array $payload): BrandingSetting
    {
        return DB::transaction(function () use ($payload): BrandingSetting {
            $branding = BrandingSetting::current();

            $branding->fill([
                'site_name' => $payload['site_name'] ?? $branding->site_name,
                'tagline' => $payload['tagline'] ?? $branding->tagline,
                'support_email' => $payload['support_email'] ?? null,
                'support_phone' => $payload['support_phone'] ?? null,
                'whatsapp_number' => $payload['whatsapp_number'] ?? null,
                'social' => $payload['social'] ?? [],
                'address_line' => $payload['address_line'] ?? $branding->address_line,
                'updated_by' => Auth::id(),
            ])->save();

            DB::afterCommit(function () use ($branding): void {
                foreach (['en', 'ar'] as $locale) {
                    Cache::forget(GetBrandingAction::CACHE_KEY.':'.$locale);
                }
                event(new PublicThemeChanged(reason: 'branding.saved', publicId: $branding->public_id));
            });

            return $branding->fresh();
        });
    }
}
