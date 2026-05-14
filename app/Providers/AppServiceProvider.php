<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Filament\Forms\Components\TextInput;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Relation::morphMap([
            'service' => Service::class,
            'vendor' => VendorProfile::class,
        ]);

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch->locales(['en', 'ar']);
        });

        RateLimiter::for('loyalty-redemption', function (Request $request): Limit {
            $maxAttempts = (int) config('loyalty.redemption_throttle.max_attempts', 20);
            $decayMinutes = (int) config('loyalty.redemption_throttle.decay_minutes', 1);
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinutes($decayMinutes, $maxAttempts)->by((string) $key);
        });

        TextInput::macro('dir', function (string $direction = 'ltr') {
            /** @var TextInput $this */

            return $this->extraInputAttributes([
                'dir' => $direction,
                'style' => $direction === 'rtl'
                    ? 'text-align: right;'
                    : 'text-align: left;',
            ]);
        });
    }
}
