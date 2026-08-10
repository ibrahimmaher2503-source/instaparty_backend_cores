<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Application\Actions\SendOtpAction;
use App\Modules\Identity\Application\Actions\VerifyPhoneAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phone OTP verification — the step that actually authenticates a newly registered
 * customer, matching the API contract where VerifyPhoneAction is what mints a token.
 */
class PhoneVerificationController
{
    public function show(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get('auth.verify_phone');

        return $phone === null
            ? redirect()->route('storefront.auth.register')
            : view('storefront.auth.verify', ['phone' => $phone]);
    }

    public function send(Request $request, SendOtpAction $action): RedirectResponse
    {
        $action->execute($this->phone($request));

        return back()->with('status', __('storefront.auth.verify.resent'));
    }

    public function verify(Request $request, VerifyPhoneAction $action): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);

        $user = $action->execute($this->phone($request), $data['code']);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget('auth.verify_phone');

        return redirect()->intended(route('storefront.home'));
    }

    private function phone(Request $request): string
    {
        return (string) $request->session()->get('auth.verify_phone', '');
    }
}
