<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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
use Rmsramos\Activitylog\ActivitylogPlugin;

class AdminPanelProvider extends PanelProvider
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
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            // Flat Filament/Resources/ paths (existing resources — do not move)
            ->discoverResources(in: app_path('Modules/Booking/Filament/Resources'), for: 'App\\Modules\\Booking\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Catalog/Filament/Resources'), for: 'App\\Modules\\Catalog\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Communication/Filament/Resources'), for: 'App\\Modules\\Communication\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Discovery/Filament/Resources'), for: 'App\\Modules\\Discovery\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Geography/Filament/Resources'), for: 'App\\Modules\\Geography\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Identity/Filament/Resources'), for: 'App\\Modules\\Identity\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Loyalty/Filament/Resources'), for: 'App\\Modules\\Loyalty\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Payments/Filament/Resources'), for: 'App\\Modules\\Payments\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Reviews/Filament/Resources'), for: 'App\\Modules\\Reviews\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Settlement/Filament/Resources'), for: 'App\\Modules\\Settlement\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Subscriptions/Filament/Resources'), for: 'App\\Modules\\Subscriptions\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Shared/Filament/Resources'), for: 'App\\Modules\\Shared\\Filament\\Resources')
            // Filament/Admin/Resources/ paths (forward-compatible; new admin resources go here)
            ->discoverResources(in: app_path('Modules/Booking/Filament/Admin/Resources'), for: 'App\\Modules\\Booking\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Catalog/Filament/Admin/Resources'), for: 'App\\Modules\\Catalog\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Communication/Filament/Admin/Resources'), for: 'App\\Modules\\Communication\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Discovery/Filament/Admin/Resources'), for: 'App\\Modules\\Discovery\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Geography/Filament/Admin/Resources'), for: 'App\\Modules\\Geography\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Identity/Filament/Admin/Resources'), for: 'App\\Modules\\Identity\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Loyalty/Filament/Admin/Resources'), for: 'App\\Modules\\Loyalty\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Payments/Filament/Admin/Resources'), for: 'App\\Modules\\Payments\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Reviews/Filament/Admin/Resources'), for: 'App\\Modules\\Reviews\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Settlement/Filament/Admin/Resources'), for: 'App\\Modules\\Settlement\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Subscriptions/Filament/Admin/Resources'), for: 'App\\Modules\\Subscriptions\\Filament\\Admin\\Resources')
            ->discoverResources(in: app_path('Modules/Shared/Filament/Admin/Resources'), for: 'App\\Modules\\Shared\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverPages(in: app_path('Modules/Catalog/Filament/Pages'), for: 'App\\Modules\\Catalog\\Filament\\Pages')
            ->discoverPages(in: app_path('Modules/Payments/Filament/Pages'), for: 'App\\Modules\\Payments\\Filament\\Pages')
            ->discoverPages(in: app_path('Modules/Reviews/Filament/Pages'), for: 'App\\Modules\\Reviews\\Filament\\Pages')
            ->discoverPages(in: app_path('Modules/Shared/Filament/Pages'), for: 'App\\Modules\\Shared\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->discoverWidgets(in: app_path('Modules/Booking/Filament/Widgets'), for: 'App\\Modules\\Booking\\Filament\\Widgets')
            ->discoverWidgets(in: app_path('Modules/Subscriptions/Filament/Widgets'), for: 'App\\Modules\\Subscriptions\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(__('admin.nav.groups.geography'))
                    ->label(fn (): string => __('admin.nav.groups.geography')),
                NavigationGroup::make(__('admin.nav.groups.identity'))
                    ->label(fn (): string => __('admin.nav.groups.identity')),
                NavigationGroup::make(__('admin.nav.groups.users'))
                    ->label(fn (): string => __('admin.nav.groups.users')),
                NavigationGroup::make(__('admin.nav.groups.vendor_onboarding'))
                    ->label(fn (): string => __('admin.nav.groups.vendor_onboarding')),
                NavigationGroup::make(__('admin.nav.groups.catalog'))
                    ->label(fn (): string => __('admin.nav.groups.catalog')),
                NavigationGroup::make(__('admin.nav.groups.services'))
                    ->label(fn (): string => __('admin.nav.groups.services')),
                NavigationGroup::make(__('admin.nav.groups.booking'))
                    ->label(fn (): string => __('admin.nav.groups.booking')),
                NavigationGroup::make(__('admin.nav.groups.payments'))
                    ->label(fn (): string => __('admin.nav.groups.payments')),
                NavigationGroup::make(__('admin.nav.groups.settlement'))
                    ->label(fn (): string => __('admin.nav.groups.settlement')),
                NavigationGroup::make('subscriptions')
                    ->label(fn (): string => __('subscription.nav_group')),
                NavigationGroup::make(__('admin.nav.groups.loyalty'))
                    ->label(fn (): string => __('admin.nav.groups.loyalty')),
                NavigationGroup::make(__('admin.nav.groups.discovery'))
                    ->label(fn (): string => __('admin.nav.groups.discovery')),
                NavigationGroup::make(__('admin.nav.groups.communication'))
                    ->label(fn (): string => __('admin.nav.groups.communication')),
                NavigationGroup::make(__('admin.nav.groups.moderation'))
                    ->label(fn (): string => __('admin.nav.groups.moderation')),
                NavigationGroup::make(__('admin.nav.groups.reports'))
                    ->label(fn (): string => __('admin.nav.groups.reports')),
                NavigationGroup::make(__('admin.nav.groups.settings'))
                    ->label(fn (): string => __('admin.nav.groups.settings')),
                NavigationGroup::make(__('admin.nav.groups.activity_logs'))
                    ->label(fn (): string => __('admin.nav.groups.activity_logs')),
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                ActivitylogPlugin::make(),

                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'ar']),
            ])
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
            ]);
    }
}
