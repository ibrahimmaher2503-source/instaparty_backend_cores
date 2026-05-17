<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Actions\GetHomepageBlocksAction;
use App\Modules\Shared\Application\Actions\SaveHomeBlockAction;
use App\Modules\Shared\Domain\Enums\HomeBlockType;
use App\Modules\Shared\Domain\Models\HomeBlock;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    foreach (['en', 'ar'] as $locale) {
        Cache::forget('theme:homepage:'.$locale);
    }
    HomeBlock::query()->delete();
});

it('returns an empty array when no blocks exist', function (): void {
    getJson('/api/v1/cms/homepage')->assertOk()->assertJsonPath('data', []);
})->group('shared', 'homepage');

it('returns only visible blocks ordered by position', function (): void {
    app(SaveHomeBlockAction::class)->execute(null, [
        'block_type' => HomeBlockType::CtaBanner->value,
        'name' => 'Cta hidden',
        'position' => 1,
        'is_visible' => false,
        'payload' => [
            'headline' => ['en' => 'Hidden', 'ar' => 'مخفي'],
            'cta_label' => ['en' => 'Go', 'ar' => 'اذهب'],
            'cta_url' => '/x',
        ],
    ]);
    app(SaveHomeBlockAction::class)->execute(null, [
        'block_type' => HomeBlockType::CtaBanner->value,
        'name' => 'Cta A',
        'position' => 2,
        'is_visible' => true,
        'payload' => [
            'headline' => ['en' => 'A', 'ar' => 'أ'],
            'cta_label' => ['en' => 'Go', 'ar' => 'اذهب'],
            'cta_url' => '/a',
        ],
    ]);
    app(SaveHomeBlockAction::class)->execute(null, [
        'block_type' => HomeBlockType::CtaBanner->value,
        'name' => 'Cta B',
        'position' => 0,
        'is_visible' => true,
        'payload' => [
            'headline' => ['en' => 'B', 'ar' => 'ب'],
            'cta_label' => ['en' => 'Go', 'ar' => 'اذهب'],
            'cta_url' => '/b',
        ],
    ]);

    foreach (['en', 'ar'] as $locale) {
        Cache::forget('theme:homepage:'.$locale);
    }

    $response = getJson('/api/v1/cms/homepage', ['Accept-Language' => 'en']);
    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    expect($data[0]['name'])->toBe('Cta B');
    expect($data[1]['name'])->toBe('Cta A');
    expect($data[0]['payload']['headline'])->toBe('B');
})->group('shared', 'homepage');

it('localises translatable payload fields', function (): void {
    app(SaveHomeBlockAction::class)->execute(null, [
        'block_type' => HomeBlockType::CtaBanner->value,
        'name' => 'Cta',
        'position' => 0,
        'is_visible' => true,
        'payload' => [
            'headline' => ['en' => 'Hello', 'ar' => 'مرحبا'],
            'cta_label' => ['en' => 'Go', 'ar' => 'اذهب'],
            'cta_url' => '/go',
        ],
    ]);

    foreach (['en', 'ar'] as $locale) {
        Cache::forget('theme:homepage:'.$locale);
    }

    getJson('/api/v1/cms/homepage', ['Accept-Language' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.0.payload.headline', 'مرحبا')
        ->assertJsonPath('data.0.payload.cta_label', 'اذهب');
})->group('shared', 'homepage');

it('rejects invalid payload for a block type', function (): void {
    expect(fn () => app(SaveHomeBlockAction::class)->execute(null, [
        'block_type' => HomeBlockType::CtaBanner->value,
        'name' => 'broken',
        'position' => 0,
        'is_visible' => true,
        'payload' => ['headline' => ['en' => 'only']], // missing required ar + cta fields
    ]))->toThrow(Illuminate\Validation\ValidationException::class);
})->group('shared', 'homepage');

it('save action busts homepage cache', function (): void {
    Cache::put('theme:homepage:en', ['stale'], 60);

    app(SaveHomeBlockAction::class)->execute(null, [
        'block_type' => HomeBlockType::CtaBanner->value,
        'name' => 'fresh',
        'position' => 0,
        'is_visible' => true,
        'payload' => [
            'headline' => ['en' => 'X', 'ar' => 'س'],
            'cta_label' => ['en' => 'Go', 'ar' => 'اذهب'],
            'cta_url' => '/x',
        ],
    ]);

    expect(Cache::has('theme:homepage:en'))->toBeFalse();
})->group('shared', 'homepage');
