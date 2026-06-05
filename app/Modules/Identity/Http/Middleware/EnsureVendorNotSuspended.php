<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vendor-portal approval-gate finding (audit 2026-06-04, approved
 * 2026-06-05): suspended vendors become READ-ONLY on the API — GETs pass
 * (wallet/bookings stay visible per the portal spec), every mutation is
 * rejected with a localized 403. Auth endpoints are outside the vendor
 * groups and unaffected.
 */
class EnsureVendorNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $vendor = $request->user()?->vendorProfile;

        if ($vendor !== null && $vendor->approval_status instanceof SuspendedState) {
            return response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [[
                    'code' => 'vendor_suspended',
                    'message' => __('identity::identity.errors.vendor_suspended'),
                ]],
            ], 403);
        }

        return $next($request);
    }
}
