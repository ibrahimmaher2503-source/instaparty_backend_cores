<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Web\LoginController;
use App\Modules\Identity\Http\Controllers\Web\PasswordResetController;
use App\Modules\Identity\Http\Controllers\Web\PhoneVerificationController;
use App\Modules\Identity\Http\Controllers\Web\RegisterController;
use Illuminate\Support\Facades\Route;

/*
| Identity storefront routes (session auth on the `web` guard).
|
| Auto-discovered by routes/web.php, which supplies the `web` middleware, the
| locale middleware, the /{locale} prefix and the `storefront.` name prefix.
|
| These sit alongside — not instead of — the token-based /api/v1/customer/*
| auth endpoints, which remain the Flutter contract. Both call the same Actions.
|
| Rate limiters `api-login` and `password-reset` are the ones already defined in
| AppServiceProvider for the API, so web and mobile share a budget per identifier.
*/

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:api-login');

        Route::get('register', [RegisterController::class, 'show'])->name('register');
        Route::post('register', [RegisterController::class, 'register']);

        Route::get('forgot', [PasswordResetController::class, 'showRequest'])->name('forgot');
        Route::post('forgot', [PasswordResetController::class, 'sendResetLink'])
            ->middleware('throttle:password-reset');

        Route::get('reset', [PasswordResetController::class, 'showReset'])->name('reset');
        Route::post('reset', [PasswordResetController::class, 'reset']);

        // Phone verification is what authenticates a new registration, mirroring the
        // API where VerifyPhoneAction mints the token. Guests must reach it.
        Route::get('verify', [PhoneVerificationController::class, 'show'])->name('verify');
        Route::post('verify', [PhoneVerificationController::class, 'verify']);
        Route::post('verify/send', [PhoneVerificationController::class, 'send'])->name('verify.send');
    });

    Route::post('logout', [LoginController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');
});
