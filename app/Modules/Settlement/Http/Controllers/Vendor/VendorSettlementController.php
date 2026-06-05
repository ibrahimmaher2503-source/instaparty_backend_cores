<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Controllers\Vendor;

use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 12.4/12.5 — settlement history. settlement_runs are
 * platform-wide reconciliation periods (no vendor dimension), so the
 * vendor-facing settlement view is their own append-only commissions
 * records: per booking item gross → commission → vendor share.
 */
class VendorSettlementController
{
    public function index(Request $request): JsonResponse
    {
        $page = Commission::query()
            ->where('vendor_profile_id', $this->vendorProfileId($request))
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return ApiResponse::success(
            collect($page->items())->map(fn (Commission $c): array => $this->row($c))->values(),
            ['next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages()],
        );
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $commission = Commission::query()
            ->where('vendor_profile_id', $this->vendorProfileId($request))
            ->where('public_id', $publicId)
            ->firstOrFail();

        return ApiResponse::success($this->row($commission));
    }

    /** @return array<string, mixed> */
    private function row(Commission $c): array
    {
        return [
            'public_id' => $c->public_id,
            'product_type' => $c->product_type instanceof \BackedEnum ? $c->product_type->value : $c->product_type,
            'gross_amount_minor' => (int) $c->gross_amount_minor,
            'commission_bps' => (int) $c->commission_bps,
            'commission_minor' => (int) $c->commission_minor,
            'vendor_share_minor' => (int) $c->vendor_share_minor,
            'reversed_amount_minor' => (int) ($c->reversed_amount_minor ?? 0),
            'currency' => (string) ($c->gross_amount_currency ?? 'EGP'),
            'status' => $c->status,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    private function vendorProfileId(Request $request): int
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return (int) $vendor->id;
    }
}
