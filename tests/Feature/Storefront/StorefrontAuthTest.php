<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Session auth for the Blade storefront, on the `web` guard.
 *
 * These sit alongside the token-based /api/v1/customer/* auth tests — both surfaces
 * call the same Identity Actions, so what is exercised here is the session plumbing
 * and the web-specific redirect behaviour, not the credential logic.
 */
function customer(array $attributes = []): User
{
    return User::factory()->asCustomer()->phoneVerified()->create($attributes + [
        'password' => Hash::make('correct-horse-battery'),
    ]);
}

it('renders the login page in both locales', function (string $locale): void {
    $this->get("/{$locale}/auth/login")
        ->assertOk()
        ->assertSee(__('storefront.auth.login.title', [], $locale));
})->with(['en', 'ar'])->group('storefront', 'auth', 'locale');

it('signs a customer in and starts a session', function (): void {
    $user = customer();

    $this->post('/en/auth/login', [
        'login' => $user->email,
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('storefront.home'));

    $this->assertAuthenticatedAs($user, 'web');
})->group('storefront', 'auth');

it('rejects bad credentials without starting a session', function (): void {
    $user = customer();

    $this->from('/en/auth/login')
        ->post('/en/auth/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ])
        ->assertRedirect('/en/auth/login')
        ->assertSessionHasErrors('login');

    $this->assertGuest('web');
})->group('storefront', 'auth');

/**
 * The reason web logout does not reuse LogoutAction.
 *
 * LogoutAction deletes the user's Sanctum tokens, which is correct for the API but
 * would sign them out of the Flutter app as a side effect of clicking "log out" in a
 * browser. Nothing about the redirect would reveal that, so it is asserted directly.
 */
it('logs out of the web session without revoking mobile API tokens', function (): void {
    $user = customer();
    $user->createToken('flutter-app');

    expect($user->tokens()->count())->toBe(1);

    $this->actingAs($user, 'web')
        ->post('/en/auth/logout')
        ->assertRedirect(route('storefront.home'));

    $this->assertGuest('web');
    expect($user->fresh()->tokens()->count())->toBe(1);
})->group('storefront', 'auth');

it('keeps signed-in customers away from the guest-only auth pages', function (string $path): void {
    $this->actingAs(customer(), 'web')
        ->get("/en/auth/{$path}")
        ->assertRedirect();
})->with(['login', 'register', 'forgot'])->group('storefront', 'auth');

/**
 * Laravel's `auth` middleware falls back to route('login') when unauthenticated, which
 * throws RouteNotFoundException unless a route is literally named `login` — every
 * storefront route name carries the `storefront.` prefix, so an alias exists for it.
 */
it('redirects guests away from authenticated-only routes without erroring', function (): void {
    $this->post('/en/auth/logout')->assertRedirect(route('login'));
})->group('storefront', 'auth');

it('registers a customer and sends them to phone verification', function (): void {
    $this->post('/en/auth/register', [
        'name' => 'Nadia Hassan',
        'phone_e164' => '+201234567890',
        'email' => 'nadia@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'accepted_terms' => '1',
        'preferred_locale' => 'en',
    ])->assertRedirect(route('storefront.auth.verify'));

    $user = User::where('phone_e164', '+201234567890')->firstOrFail();

    expect($user->hasRole('customer'))->toBeTrue();

    // Registration must NOT authenticate — phone verification is the login step,
    // mirroring the API where VerifyPhoneAction is what mints a token.
    $this->assertGuest('web');
})->group('storefront', 'auth');

it('rejects registration that does not accept the terms', function (): void {
    $this->from('/en/auth/register')
        ->post('/en/auth/register', [
            'name' => 'Nadia Hassan',
            'phone_e164' => '+201234567891',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])
        ->assertSessionHasErrors('accepted_terms');

    expect(User::where('phone_e164', '+201234567891')->exists())->toBeFalse();
})->group('storefront', 'auth');

/**
 * The neutral response is a deliberate anti-enumeration measure, not a stub — an
 * attacker must not be able to tell registered identifiers from unregistered ones.
 */
it('reports the same result for known and unknown reset identifiers', function (): void {
    $known = customer(['email' => 'known@example.com']);

    $first = $this->from('/en/auth/forgot')
        ->post('/en/auth/forgot', ['identifier' => $known->email])
        ->assertRedirect('/en/auth/forgot');

    $second = $this->from('/en/auth/forgot')
        ->post('/en/auth/forgot', ['identifier' => 'nobody@example.com'])
        ->assertRedirect('/en/auth/forgot');

    expect(session('status'))->toBe(__('storefront.auth.forgot.success_neutral'));
    expect($first->getStatusCode())->toBe($second->getStatusCode());
})->group('storefront', 'auth');

it('sends someone with no pending verification back to register', function (): void {
    $this->get('/en/auth/verify')->assertRedirect(route('storefront.auth.register'));
})->group('storefront', 'auth');
