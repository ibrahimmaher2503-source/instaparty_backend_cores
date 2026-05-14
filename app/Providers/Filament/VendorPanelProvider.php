<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Modules\Identity\Filament\Vendor\Pages\RegisterVendorPage;
use App\Modules\Identity\Http\Middleware\CheckVendorSuspension;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use App\Modules\Shared\Filament\Vendor\Pages\VendorDashboardPage;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class VendorPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
            $switch
                ->locales(['en', 'ar'])
                ->visible(insidePanels: true);
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('vendor')
            ->path('vendor')
            ->login()
            ->registration(RegisterVendorPage::class)
            ->emailVerification()
            ->homeUrl('/vendor')
            ->passwordReset()
            ->brandName('InstaParty Vendor')
            ->colors([
                'primary' => Color::Orange,
            ])
            ->font('Cairo')
            // Resources
            ->discoverResources(
                in: app_path('Modules/Identity/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Identity\\Filament\\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Catalog/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Catalog\\Filament\\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Booking/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Booking\\Filament\\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Settlement/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Settlement\\Filament\\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Communication/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Communication\\Filament\\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Reviews/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Reviews\\Filament\\Vendor\\Resources'
            )
            ->discoverResources(
                in: app_path('Modules/Loyalty/Filament/Vendor/Resources'),
                for: 'App\\Modules\\Loyalty\\Filament\\Vendor\\Resources'
            )
            // Pages
            ->discoverPages(
                in: app_path('Modules/Identity/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Identity\\Filament\\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Catalog/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Catalog\\Filament\\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Booking/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Booking\\Filament\\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Settlement/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Settlement\\Filament\\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Communication/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Communication\\Filament\\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Reviews/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Reviews\\Filament\\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Loyalty/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Loyalty\\Filament\\Vendor\\Pages'
            )
            ->discoverPages(
                in: app_path('Modules/Shared/Filament/Vendor/Pages'),
                for: 'App\\Modules\\Shared\\Filament\\Vendor\\Pages'
            )
            // Widgets
            ->discoverWidgets(
                in: app_path('Modules/Booking/Filament/Vendor/Widgets'),
                for: 'App\\Modules\\Booking\\Filament\\Vendor\\Widgets'
            )
            ->discoverWidgets(
                in: app_path('Modules/Identity/Filament/Vendor/Widgets'),
                for: 'App\\Modules\\Identity\\Filament\\Vendor\\Widgets'
            )
            ->discoverWidgets(
                in: app_path('Modules/Shared/Filament/Vendor/Widgets'),
                for: 'App\\Modules\\Shared\\Filament\\Vendor\\Widgets'
            )
            ->pages([
                VendorDashboardPage::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('profile')
                    ->label(fn (): string => __('vendor-portal.nav.groups.profile')),
                NavigationGroup::make('services')
                    ->label(fn (): string => __('vendor-portal.nav.groups.services')),
                NavigationGroup::make('bookings')
                    ->label(fn (): string => __('vendor-portal.nav.groups.bookings')),
                NavigationGroup::make('finance')
                    ->label(fn (): string => __('vendor-portal.nav.groups.finance')),
                NavigationGroup::make('engagement')
                    ->label(fn (): string => __('vendor-portal.nav.groups.engagement')),
                NavigationGroup::make('settings')
                    ->label(fn (): string => __('vendor-portal.nav.groups.settings')),
            ])
            ->plugins([
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'ar']),
            ])
            ->authGuard('web')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                CheckVendorSuspension::class,
            ]);
    }
}
