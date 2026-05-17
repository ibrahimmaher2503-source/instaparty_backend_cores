<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Actions\GetNavigationMenuAction;
use App\Modules\Shared\Application\Actions\SaveNavigationMenuAction;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Enums\NavigationTargetType;
use App\Modules\Shared\Domain\Models\NavigationMenu;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    foreach (NavigationSlot::cases() as $slot) {
        foreach (['en', 'ar'] as $locale) {
            Cache::forget('theme:menus:'.$slot->value.':'.$locale);
        }
    }
    NavigationMenu::query()->delete();
});

it('returns 422 for unknown slot', function (): void {
    getJson('/api/v1/theme/menus?slot=garbage')->assertStatus(422);
})->group('shared', 'menus');

it('returns empty items when slot has no menu yet', function (): void {
    getJson('/api/v1/theme/menus?slot=header')
        ->assertOk()
        ->assertJsonPath('data.slot', 'header')
        ->assertJsonPath('data.items', []);
})->group('shared', 'menus');

it('returns nested menu tree in the requested locale', function (): void {
    app(SaveNavigationMenuAction::class)->execute(
        slot: NavigationSlot::Header,
        name: 'Main',
        items: [
            [
                'label' => ['en' => 'Home', 'ar' => 'الرئيسية'],
                'target_type' => NavigationTargetType::InternalPath->value,
                'target_value' => '/',
            ],
            [
                'label' => ['en' => 'Categories', 'ar' => 'التصنيفات'],
                'target_type' => NavigationTargetType::InternalPath->value,
                'target_value' => '/categories',
                'children' => [
                    [
                        'label' => ['en' => 'Cakes', 'ar' => 'كيك'],
                        'target_type' => NavigationTargetType::Category->value,
                        'target_value' => '01H0000000000000000000CAKE',
                    ],
                ],
            ],
        ],
    );

    foreach (['en', 'ar'] as $locale) {
        Cache::forget('theme:menus:header:'.$locale);
    }

    getJson('/api/v1/theme/menus?slot=header', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.items.0.label', 'Home')
        ->assertJsonPath('data.items.1.label', 'Categories')
        ->assertJsonPath('data.items.1.children.0.label', 'Cakes');

    getJson('/api/v1/theme/menus?slot=header', ['Accept-Language' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.items.0.label', 'الرئيسية')
        ->assertJsonPath('data.items.1.children.0.label', 'كيك');
})->group('shared', 'menus');

it('save action busts cache for the slot', function (): void {
    Cache::put('theme:menus:header:en', ['stale'], 60);

    app(SaveNavigationMenuAction::class)->execute(
        slot: NavigationSlot::Header,
        name: 'Main',
        items: [[
            'label' => ['en' => 'Home', 'ar' => 'الرئيسية'],
            'target_type' => NavigationTargetType::InternalPath->value,
            'target_value' => '/',
        ]],
    );

    expect(Cache::has('theme:menus:header:en'))->toBeFalse();
})->group('shared', 'menus');

it('rejects items missing required EN label', function (): void {
    expect(fn () => app(SaveNavigationMenuAction::class)->execute(
        slot: NavigationSlot::Header,
        name: 'Main',
        items: [[
            'label' => ['ar' => 'فقط عربي'],
            'target_type' => NavigationTargetType::InternalPath->value,
            'target_value' => '/',
        ]],
    ))->toThrow(Illuminate\Validation\ValidationException::class);
})->group('shared', 'menus');
