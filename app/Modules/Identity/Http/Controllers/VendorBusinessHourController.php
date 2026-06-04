<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\UpsertVendorBusinessHoursAction;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Http\Requests\UpsertBusinessHoursRequest;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorBusinessHourController extends Controller
{
    /** 8.1 — read the weekly schedule (the PUT existed without a GET). */
    public function index(Request $request): JsonResponse
    {
        $vendorProfile = $request->user()?->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $hours = VendorBusinessHour::query()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->orderBy('day_of_week')
            ->get(['day_of_week', 'opens_at', 'closes_at'])
            ->map(fn ($row): array => [
                'day_of_week' => (int) ($row->day_of_week instanceof \BackedEnum ? $row->day_of_week->value : $row->day_of_week),
                'opens_at' => $row->opens_at !== null ? substr((string) $row->opens_at, 0, 5) : null,
                'closes_at' => $row->closes_at !== null ? substr((string) $row->closes_at, 0, 5) : null,
            ])
            ->values();

        return ApiResponse::success(['hours' => $hours]);
    }

    public function update(UpsertBusinessHoursRequest $request, UpsertVendorBusinessHoursAction $action): JsonResponse
    {
        $vendorProfile = $request->user()?->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $hours = $action->execute($vendorProfile, $request->input('hours'));

        return ApiResponse::success(['hours' => $hours]);
    }
}
