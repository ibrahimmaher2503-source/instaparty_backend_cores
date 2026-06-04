<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/v1/vendor/compliance (G3) — per-product-type approval status,
 * document statuses, and expiry alerts (ADR-0021 lifecycle).
 */
class VendorComplianceController extends Controller
{
    private const EXPIRY_ALERT_DAYS = 30;

    public function __invoke(Request $request): JsonResponse
    {
        $vendorProfile = $request->user()?->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $activeTypes = VendorApprovedProductType::query()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->active()
            ->get()
            ->keyBy(fn (VendorApprovedProductType $row) => $row->product_type->value);

        $documents = VendorDocument::query()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->orderByDesc('id')
            ->get();

        $today = today();

        return ApiResponse::success([
            'approval_status' => $vendorProfile->approval_status->getMorphClass(),
            'product_types' => collect(ProductType::cases())->map(fn (ProductType $type): array => [
                'product_type' => $type->value,
                'approved' => $activeTypes->has($type->value),
                'approved_at' => $activeTypes->get($type->value)?->approved_at?->toIso8601String(),
            ])->values(),
            'documents' => $documents->map(fn (VendorDocument $doc): array => [
                'public_id' => $doc->public_id,
                'doc_type' => $doc->doc_type->value,
                'status' => $doc->status->value,
                'is_critical' => (bool) $doc->is_critical,
                'expires_at' => $doc->expires_at?->toDateString(),
                'is_expired' => $doc->expires_at !== null && $doc->expires_at->lt($today),
                'expires_soon' => $doc->expires_at !== null
                    && $doc->expires_at->gte($today)
                    && $doc->expires_at->lte($today->copy()->addDays(self::EXPIRY_ALERT_DAYS)),
            ])->values(),
            'alerts' => [
                'expired_documents' => $documents
                    ->filter(fn (VendorDocument $doc) => $doc->expires_at !== null && $doc->expires_at->lt($today))
                    ->count(),
                'expiring_documents' => $documents
                    ->filter(fn (VendorDocument $doc) => $doc->expires_at !== null
                        && $doc->expires_at->gte($today)
                        && $doc->expires_at->lte($today->copy()->addDays(self::EXPIRY_ALERT_DAYS)))
                    ->count(),
            ],
        ]);
    }
}
