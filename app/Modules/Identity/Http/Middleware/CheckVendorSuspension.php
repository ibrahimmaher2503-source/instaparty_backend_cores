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

        $profile = $user?->vendorProfile;

        if ($profile && $this->resolveApprovalStatus($profile) === 'suspended') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('identity.account_suspended'),
                ], 403);
            }

            try {
                $suspensionRoute = route('filament.vendor.pages.account-suspended', [], false);
            } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException $e) {
                $suspensionRoute = '/vendor/account-suspended';
            }

            // Allow access to the suspension page itself to avoid redirect loops.
            if ($request->is(ltrim($suspensionRoute, '/'))) {
                return $next($request);
            }

            return redirect($suspensionRoute);
        }

        return $next($request);
    }

    private function resolveApprovalStatus(mixed $profile): string
    {
        $raw = $profile->approval_status;

        if ($raw instanceof \BackedEnum) {
            return $raw->value;
        }

        if (is_object($raw) && method_exists($raw, 'getValue')) {
            return $raw->getValue();
        }

        return (string) $raw;
    }
}
