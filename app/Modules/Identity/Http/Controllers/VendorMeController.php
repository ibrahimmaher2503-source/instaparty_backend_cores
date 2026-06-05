<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/v1/vendor/me (G2) — role-aware identity payload for the vendor
 * app. POST /api/v1/login responds with CustomerResource, which carries no
 * vendor context; this endpoint is what the vendor app calls after login.
 *
 * @group Vendor - Profile
 */
class VendorMeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendorProfile = $user?->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return ApiResponse::success([
            'public_id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_e164' => $user->phone_e164,
            'preferred_locale' => $user->preferred_locale,
            'timezone' => $user->timezone,
            'roles' => $user->getRoleNames(),
            'vendor_profile' => [
                'public_id' => $vendorProfile->public_id,
                'business_name' => $vendorProfile->getTranslations('business_name'),
                'slug' => $vendorProfile->slug,
                'approval_status' => $vendorProfile->approval_status->getMorphClass(),
                'approved_product_types' => VendorApprovedProductType::query()
                    ->where('vendor_profile_id', $vendorProfile->id)
                    ->active()
                    ->get()
                    ->map(fn (VendorApprovedProductType $row) => $row->product_type->value)
                    ->values(),
            ],
        ]);
    }
}
