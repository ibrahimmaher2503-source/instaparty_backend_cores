<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckVendorSuspension
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === 'suspended') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('identity.account_suspended'),
                ], 403);
            }

            $suspensionRoute = route('filament.vendor.pages.account-suspended', [], false);

            // Allow access to the suspension page itself to avoid redirect loops.
            if ($request->is(ltrim($suspensionRoute, '/'))) {
                return $next($request);
            }

            return redirect($suspensionRoute);
        }

        return $next($request);
    }
}
