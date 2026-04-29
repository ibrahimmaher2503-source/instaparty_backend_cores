<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\LogoutAction;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('deletes a PersonalAccessToken when one is bound to the user', function (): void {
    $user = User::factory()->create();
    $tokenInstance = $user->createToken('test');
    $accessToken = $tokenInstance->accessToken;
    $user->withAccessToken($accessToken);

    expect($accessToken)->toBeInstanceOf(PersonalAccessToken::class);

    (new LogoutAction)->execute($user);

    expect($user->tokens()->count())->toBe(0);
})->group('identity', 'us1', 'arch');

it('falls back to deleting all tokens when current token is a TransientToken (cookie auth)', function (): void {
    $user = User::factory()->create();
    $user->createToken('keepalive');
    $user->createToken('other');
    $user->withAccessToken(new TransientToken);

    expect($user->currentAccessToken())->toBeInstanceOf(TransientToken::class);
    expect($user->tokens()->count())->toBe(2);

    (new LogoutAction)->execute($user);

    expect($user->tokens()->count())->toBe(0);
})->group('identity', 'us1', 'arch');
