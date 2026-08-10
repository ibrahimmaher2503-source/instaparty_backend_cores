<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Application\Actions\ConfirmPasswordResetAction;
use App\Modules\Identity\Application\Actions\RequestPasswordResetAction;
use App\Modules\Identity\Http\Requests\ConfirmPasswordResetRequest;
use App\Modules\Identity\Http\Requests\RequestPasswordResetRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordResetController
{
    public function showRequest(): View
    {
        return view('storefront.auth.forgot');
    }

    /**
     * Always reports the same neutral outcome whether or not the identifier matched an
     * account — the copy (auth.forgot.success_neutral) was written that way on purpose,
     * so the page cannot be used to enumerate registered emails or phone numbers.
     */
    public function sendResetLink(RequestPasswordResetRequest $request, RequestPasswordResetAction $action): RedirectResponse
    {
        $action->execute($request->toDTO());

        return back()->with('status', __('storefront.auth.forgot.success_neutral'));
    }

    public function showReset(Request $request): View
    {
        return view('storefront.auth.reset', [
            'identifier' => (string) $request->query('identifier', ''),
            'token' => (string) $request->query('token', ''),
        ]);
    }

    public function reset(ConfirmPasswordResetRequest $request, ConfirmPasswordResetAction $action): RedirectResponse
    {
        $action->execute($request->toDTO());

        return redirect()->route('storefront.auth.login')
            ->with('status', __('storefront.auth.reset.success'));
    }
}
