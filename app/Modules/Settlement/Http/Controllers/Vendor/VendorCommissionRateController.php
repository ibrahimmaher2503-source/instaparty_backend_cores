<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Controllers\Vendor;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Domain\Contracts\CommissionRateResolver;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 10.3 + 10.4 — the vendor's OWN resolved commission rates
 * (per approved type × offered category, most-specific-wins) and a payout
 * calculator preview. Never exposes other vendors' anything: rates are
 * resolved through the same CommissionRateResolver that prices bookings.
 *
 * @group Vendor - Pricing
 */
class VendorCommissionRateController
{
    public function __construct(private readonly CommissionRateResolver $resolver) {}

    /** 10.3 — resolved bps per (approved type × offered category). */
    public function index(Request $request): JsonResponse
    {
        $vendor = $this->vendorProfile($request);

        $approvedTypes = $vendor->approvedTypes->pluck('product_type');

        $categoryIds = DB::table('services')
            ->where('vendor_profile_id', $vendor->id)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('category_id')
            ->filter()
            ->values();

        // Resolve category public_ids once (was a per-iteration query → N+1).
        $publicIds = DB::table('categories')
            ->whereIn('id', $categoryIds)
            ->pluck('public_id', 'id');

        $rates = [];
        foreach ($approvedTypes as $type) {
            // Global/default rate for the type.
            $rates[] = [
                'category_public_id' => null,
                'product_type' => $type->value,
                'commission_bps' => $this->resolver->resolve(null, $type),
            ];

            foreach ($categoryIds as $categoryId) {
                $rates[] = [
                    'category_public_id' => $publicIds[$categoryId] ?? null,
                    'product_type' => $type->value,
                    'commission_bps' => $this->resolver->resolve((int) $categoryId, $type),
                ];
            }
        }

        return ApiResponse::success($rates);
    }

    /** 10.4 — preview the commission/payout split for a hypothetical price. */
    public function calculator(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'product_type' => ['required', 'string', 'in:rental,sale,digital'],
            'category_public_id' => ['nullable', 'string', 'max:26'],
        ]);

        $this->vendorProfile($request); // ensure vendor context

        $categoryId = isset($validated['category_public_id'])
            ? DB::table('categories')->where('public_id', $validated['category_public_id'])->value('id')
            : null;

        $bps = $this->resolver->resolve(
            $categoryId !== null ? (int) $categoryId : null,
            ProductType::from($validated['product_type']),
        ) ?? 0;

        $amount = (int) $validated['amount_minor'];
        $commission = intdiv($amount * $bps, 10000);

        return ApiResponse::success([
            'amount_minor' => $amount,
            'commission_bps' => $bps,
            'commission_minor' => $commission,
            'vendor_payout_minor' => $amount - $commission,
            'currency' => 'EGP',
        ]);
    }

    private function vendorProfile(Request $request): VendorProfile
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return $vendor->loadMissing('approvedTypes');
    }
}
