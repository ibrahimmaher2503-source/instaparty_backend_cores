<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Actions\GetActiveDesignTokensAction;
use App\Modules\Shared\Application\Actions\SaveDesignTokenAction;
use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use App\Modules\Shared\Domain\Models\DesignToken;
use App\Modules\Shared\Domain\Schemas\DesignTokenSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Cache::forget(GetActiveDesignTokensAction::CACHE_KEY);
    DesignToken::query()->delete();
});

it('creates a new design-token row with validated payload', function (): void {
    $token = app(SaveDesignTokenAction::class)->execute(
        token: null,
        name: 'default',
        tokens: DesignTokenSchema::default(),
    );

    expect($token->exists)->toBeTrue();
    expect($token->public_id)->not->toBeEmpty();
    expect($token->tokens['version'])->toBe(DesignTokenSchema::CURRENT_VERSION);
})->group('shared', 'theme');

it('rejects tokens missing a required color shade', function (): void {
    $tokens = DesignTokenSchema::default();
    unset($tokens['colors']['primary']['500']);

    expect(fn () => app(SaveDesignTokenAction::class)->execute(
        token: null,
        name: 'broken',
        tokens: $tokens,
    ))->toThrow(ValidationException::class);
})->group('shared', 'theme');

it('rejects tokens with an invalid hex color', function (): void {
    $tokens = DesignTokenSchema::default();
    $tokens['colors']['primary']['500'] = 'not-a-hex';

    expect(fn () => app(SaveDesignTokenAction::class)->execute(
        token: null,
        name: 'invalid-hex',
        tokens: $tokens,
    ))->toThrow(ValidationException::class);
})->group('shared', 'theme');

it('does not bust cache or fire event when saving an inactive token', function (): void {
    Event::fake([PublicThemeChanged::class]);

    Cache::put(GetActiveDesignTokensAction::CACHE_KEY, ['x'], 60);

    app(SaveDesignTokenAction::class)->execute(
        token: null,
        name: 'draft',
        tokens: DesignTokenSchema::default(),
    );

    expect(Cache::get(GetActiveDesignTokensAction::CACHE_KEY))->toBe(['x']);
    Event::assertNotDispatched(PublicThemeChanged::class);
})->group('shared', 'theme');
