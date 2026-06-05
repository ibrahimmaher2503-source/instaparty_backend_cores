<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Domain\Models\VendorBlockedDate;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 8.3–8.5 — blocked dates (holidays).
 */
class VendorBlockedDateController
{
    public function index(Request $request): JsonResponse
    {
        $rows = VendorBlockedDate::query()
            ->where('vendor_profile_id', $this->vendorProfile($request)->id)
            ->where('blocked_date', '>=', now()->toDateString())
            ->orderBy('blocked_date')
            ->get()
            ->map(fn (VendorBlockedDate $row): array => [
                'public_id' => $row->public_id,
                'blocked_date' => $row->blocked_date?->toDateString(),
                'reason' => $row->getTranslation('reason', app()->getLocale(), useFallbackLocale: true) ?: null,
            ])
            ->values();

        return ApiResponse::success($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blocked_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['nullable', 'array'],
            'reason.en' => ['nullable', 'string', 'max:255'],
            'reason.ar' => ['nullable', 'string', 'max:255'],
        ]);

        $vendor = $this->vendorProfile($request);

        $row = DB::transaction(fn (): VendorBlockedDate => VendorBlockedDate::query()->firstOrCreate(
            ['vendor_profile_id' => $vendor->id, 'blocked_date' => $validated['blocked_date']],
            ['public_id' => (string) Str::ulid(), 'reason' => $validated['reason'] ?? null],
        ));

        return ApiResponse::success(['public_id' => $row->public_id, 'blocked_date' => $validated['blocked_date']], [], 201);
    }

    public function destroy(Request $request, string $publicId): JsonResponse
    {
        $row = VendorBlockedDate::query()
            ->where('vendor_profile_id', $this->vendorProfile($request)->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        DB::transaction(fn () => $row->delete());

        return ApiResponse::success(null);
    }

    private function vendorProfile(Request $request): VendorProfile
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return $vendor;
    }
}
