<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Application\Actions\RegisterCustomerAction;
use App\Modules\Identity\Http\Requests\RegisterCustomerRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RegisterController
{
    public function show(): View
    {
        return view('storefront.auth.register');
    }

    /**
     * Reuses the API's RegisterCustomerRequest unchanged — same rules, same DTO, same
     * Action (which assigns the `customer` role). Laravel renders validation failures
     * as a redirect-back for web and JSON for the API, so one FormRequest serves both.
     *
     * Registration deliberately does NOT start a session. It mirrors the API contract,
     * where register returns no token and phone verification is what authenticates
     * (VerifyPhoneAction is the step that mints one). Logging in here would also bounce
     * the user straight off the guest-only verify page.
     */
    public function register(RegisterCustomerRequest $request, RegisterCustomerAction $action): RedirectResponse
    {
        $user = $action->execute($request->toDTO());

        // Kept in the session rather than the URL so the phone number is not logged
        // in access logs or leaked via Referer.
        $request->session()->put('auth.verify_phone', $user->phone_e164);

        return redirect()->route('storefront.auth.verify');
    }
}
