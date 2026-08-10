<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Application\Actions\LoginAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController
{
    public function show(): View
    {
        return view('storefront.auth.login');
    }

    /**
     * Credential checking stays in LoginAction — the same one the API uses, called
     * with useToken: false (a mode it already supported). Only the session plumbing
     * lives here, because a session is an HTTP concern an Action should not know about.
     *
     * A failed login throws ValidationException from the Action, which Laravel turns
     * into a redirect-back-with-errors for web requests and JSON for the API.
     */
    public function login(Request $request, LoginAction $action): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $result = $action->execute(
            login: $credentials['login'],
            password: $credentials['password'],
            ip: $request->ip(),
            useToken: false,
        );

        Auth::guard('web')->login($result['user'], remember: $request->boolean('remember'));

        // Rotate the session ID so a pre-login session cannot be fixated.
        $request->session()->regenerate();

        return redirect()->intended(route('storefront.home'));
    }

    /**
     * Deliberately does NOT call LogoutAction.
     *
     * LogoutAction deletes the user's Sanctum tokens, which is right for the API but
     * would sign them out of the Flutter app as a side effect of clicking "log out"
     * in a web browser. Web logout is session-only.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home');
    }
}
