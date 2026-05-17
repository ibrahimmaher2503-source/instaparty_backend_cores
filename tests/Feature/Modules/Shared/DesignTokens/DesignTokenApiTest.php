<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Actions\GetActiveDesignTokensAction;
use App\Modules\Shared\Domain\Models\DesignToken;
use App\Modules\Shared\Domain\Schemas\DesignTokenSchema;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    Cache::forget(GetActiveDesignTokensAction::CACHE_KEY);
    DesignToken::query()->delete();
});

it('returns default tokens when no active row exists', function (): void {
    getJson('/api/v1/theme/tokens')
        ->assertOk()
        ->assertJsonPath('data.name', 'default')
        ->assertJsonPath('data.tokens.version', DesignTokenSchema::CURRENT_VERSION)
        ->assertJsonStructure([
            'data' => [
                'public_id',
                'name',
                'tokens' => ['version', 'colors', 'typography', 'spacing', 'radius', 'shadow', 'mode'],
                'updated_at',
            ],
            'meta',
            'errors',
        ]);
})->group('shared', 'theme');

it('returns the active token row when one exists', function (): void {
    $tokens = DesignTokenSchema::default();
    $tokens['colors']['primary']['500'] = '#FF0080';

    DesignToken::create([
        'name' => 'brand-2026',
        'is_active' => true,
        'tokens' => $tokens,
    ]);

    getJson('/api/v1/theme/tokens')
        ->assertOk()
        ->assertJsonPath('data.name', 'brand-2026')
        ->assertJsonPath('data.tokens.colors.primary.500', '#FF0080');
})->group('shared', 'theme');

it('returns an ETag header and respects If-None-Match', function (): void {
    DesignToken::create([
        'name' => 'foo',
        'is_active' => true,
        'tokens' => DesignTokenSchema::default(),
    ]);

    $first = getJson('/api/v1/theme/tokens');
    $first->assertOk();
    $etag = $first->headers->get('ETag');

    expect($etag)->not->toBeNull();

    getJson('/api/v1/theme/tokens', ['If-None-Match' => $etag])
        ->assertStatus(304);
})->group('shared', 'theme');

it('caches the active tokens between requests', function (): void {
    DesignToken::create([
        'name' => 'cached',
        'is_active' => true,
        'tokens' => DesignTokenSchema::default(),
    ]);

    getJson('/api/v1/theme/tokens')->assertOk();

    expect(Cache::has(GetActiveDesignTokensAction::CACHE_KEY))->toBeTrue();
})->group('shared', 'theme');
