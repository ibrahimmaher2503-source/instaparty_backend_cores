<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\ChangeVendorPasswordAction;
use App\Modules\Identity\Application\Actions\UpdateVendorEmailAction;
use App\Modules\Identity\Application\Actions\UpdateVendorPhoneAction;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ===========================================================================
// ChangeVendorPasswordAction
// ===========================================================================

it('changes vendor password when current password is correct', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    app(ChangeVendorPasswordAction::class)
        ->execute($user, 'old-password', 'new-secret-123');

    $user->refresh();
    expect(Hash::check('new-secret-123', $user->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->password))->toBeFalse();
})->group('identity', 'vendor-account');

it('throws a ValidationException when current password is wrong', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    expect(fn () => app(ChangeVendorPasswordAction::class)
        ->execute($user, 'wrong-password', 'new-secret-123')
    )->toThrow(ValidationException::class);
})->group('identity', 'vendor-account');

it('ValidationException contains the current_password key when password is wrong', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    try {
        app(ChangeVendorPasswordAction::class)
            ->execute($user, 'bad-password', 'new-secret-123');
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('current_password');
    }
})->group('identity', 'vendor-account');

it('does not update password when current password is wrong', function (): void {
    $originalHash = Hash::make('correct-password');
    $user = User::factory()->create(['password' => $originalHash]);

    try {
        app(ChangeVendorPasswordAction::class)
            ->execute($user, 'wrong', 'new-secret');
    } catch (ValidationException) {
        // expected
    }

    $user->refresh();
    expect(Hash::check('correct-password', $user->password))->toBeTrue();
})->group('identity', 'vendor-account');

// ===========================================================================
// UpdateVendorEmailAction
// ===========================================================================

it('updates vendor email to a new valid address', function (): void {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    app(UpdateVendorEmailAction::class)
        ->execute($user, 'new@example.com');

    $user->refresh();
    expect($user->email)->toBe('new@example.com');
})->group('identity', 'vendor-account');

it('clears email_verified_at after updating the email', function (): void {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    app(UpdateVendorEmailAction::class)
        ->execute($user, 'new@example.com');

    $user->refresh();
    expect($user->email_verified_at)->toBeNull();
})->group('identity', 'vendor-account');

it('throws a database error when the new email is already taken by another user', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $user = User::factory()->create(['email' => 'mine@example.com']);

    expect(fn () => app(UpdateVendorEmailAction::class)
        ->execute($user, 'taken@example.com')
    )->toThrow(Throwable::class);
})->group('identity', 'vendor-account');

it('persists the new email to the database', function (): void {
    $user = User::factory()->create(['email' => 'before@example.com']);

    app(UpdateVendorEmailAction::class)
        ->execute($user, 'after@example.com');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'after@example.com',
    ]);
})->group('identity', 'vendor-account');

// ===========================================================================
// UpdateVendorPhoneAction
// ===========================================================================

it('updates vendor phone number', function (): void {
    $user = User::factory()->create([
        'phone_e164' => '+201000000001',
        'phone_verified_at' => now(),
    ]);

    app(UpdateVendorPhoneAction::class)
        ->execute($user, '+201000000002');

    $user->refresh();
    expect($user->phone_e164)->toBe('+201000000002');
})->group('identity', 'vendor-account');

it('clears phone_verified_at after updating the phone number', function (): void {
    $user = User::factory()->create([
        'phone_e164' => '+201000000001',
        'phone_verified_at' => now(),
    ]);

    app(UpdateVendorPhoneAction::class)
        ->execute($user, '+201000000099');

    $user->refresh();
    expect($user->phone_verified_at)->toBeNull();
})->group('identity', 'vendor-account');

it('persists the new phone number to the database', function (): void {
    $user = User::factory()->create(['phone_e164' => '+201000000001']);

    app(UpdateVendorPhoneAction::class)
        ->execute($user, '+201111111111');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'phone_e164' => '+201111111111',
    ]);
})->group('identity', 'vendor-account');

it('can update phone to a different valid E.164 number without error', function (): void {
    $user = User::factory()->create(['phone_e164' => '+201000000001']);

    expect(fn () => app(UpdateVendorPhoneAction::class)
        ->execute($user, '+441234567890')
    )->not->toThrow(Throwable::class);

    $user->refresh();
    expect($user->phone_e164)->toBe('+441234567890');
})->group('identity', 'vendor-account');
