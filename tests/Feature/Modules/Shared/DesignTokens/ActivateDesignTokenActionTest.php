<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Actions\ActivateDesignTokenAction;
use App\Modules\Shared\Application\Actions\GetActiveDesignTokensAction;
use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use App\Modules\Shared\Domain\Models\DesignToken;
use App\Modules\Shared\Domain\Schemas\DesignTokenSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Cache::forget(GetActiveDesignTokensAction::CACHE_KEY);
    DesignToken::query()->delete();
});

it('activates a token and deactivates all others', function (): void {
    $a = DesignToken::create([
        'name' => 'a', 'is_active' => true, 'tokens' => DesignTokenSchema::default(),
    ]);
    $b = DesignToken::create([
        'name' => 'b', 'is_active' => false, 'tokens' => DesignTokenSchema::default(),
    ]);

    app(ActivateDesignTokenAction::class)->execute($b);

    expect($a->fresh()->is_active)->toBeFalse();
    expect($b->fresh()->is_active)->toBeTrue();
})->group('shared', 'theme');

it('busts the cache and fires PublicThemeChanged on activate', function (): void {
    Event::fake([PublicThemeChanged::class]);

    Cache::put(GetActiveDesignTokensAction::CACHE_KEY, ['stale'], 60);

    $token = DesignToken::create([
        'name' => 'fresh', 'is_active' => false, 'tokens' => DesignTokenSchema::default(),
    ]);

    app(ActivateDesignTokenAction::class)->execute($token);

    expect(Cache::has(GetActiveDesignTokensAction::CACHE_KEY))->toBeFalse();
    Event::assertDispatched(PublicThemeChanged::class, fn (PublicThemeChanged $e) => $e->reason === 'tokens.activated'
            && $e->publicId === $token->public_id
    );
})->group('shared', 'theme');

it('public API reflects newly activated tokens after cache bust', function (): void {
    $first = DesignToken::create([
        'name' => 'first', 'is_active' => true,
        'tokens' => array_merge(DesignTokenSchema::default(), [
            'colors' => DesignTokenSchema::default()['colors'],
        ]),
    ]);

    $newTokens = DesignTokenSchema::default();
    $newTokens['colors']['primary']['500'] = '#0000FF';

    $second = DesignToken::create([
        'name' => 'second', 'is_active' => false, 'tokens' => $newTokens,
    ]);

    // Warm cache with first
    app(GetActiveDesignTokensAction::class)->execute();
    expect(Cache::has(GetActiveDesignTokensAction::CACHE_KEY))->toBeTrue();

    app(ActivateDesignTokenAction::class)->execute($second);

    // Cache was busted; next call returns second's tokens
    $payload = app(GetActiveDesignTokensAction::class)->execute();
    expect($payload['name'])->toBe('second');
    expect($payload['tokens']['colors']['primary']['500'])->toBe('#0000FF');

    unset($first); // suppress unused
})->group('shared', 'theme');
