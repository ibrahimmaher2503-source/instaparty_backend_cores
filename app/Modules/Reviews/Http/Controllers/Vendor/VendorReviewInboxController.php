<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Vendor;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use App\Modules\Reviews\Http\Resources\VendorReviewInboxResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorReviewInboxController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::in(['service', 'vendor'])],
            'status' => ['nullable', Rule::in(['new', 'responded', 'all'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $vendor = $this->vendorProfile($request);
        $type = ReviewType::from($request->query('type', 'service'));
        $status = (string) $request->query('status', 'all');
        $perPage = (int) $request->query('per_page', 25);

        $query = match ($type) {
            ReviewType::Service => ServiceReview::query()
                ->approved()
                ->whereHas('service', fn (Builder $q) => $q->where('vendor_profile_id', $vendor->id))
                ->with(['service', 'reviewer', 'vendorResponse']),
            ReviewType::Vendor => VendorReview::query()
                ->approved()
                ->where('vendor_profile_id', $vendor->id)
                ->with(['reviewer', 'vendorResponse']),
        };

        $query = match ($status) {
            'new' => $query->whereDoesntHave('vendorResponse'),
            'responded' => $query->whereHas('vendorResponse'),
            default => $query,
        };

        $paginator = $query->latest('id')->paginate($perPage);

        return ApiResponse::success(
            VendorReviewInboxResource::collection($paginator->getCollection()),
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function show(Request $request, string $reviewType, string $publicId): JsonResponse
    {
        $vendor = $this->vendorProfile($request);
        $type = ReviewType::from($reviewType);

        $review = match ($type) {
            ReviewType::Service => ServiceReview::query()
                ->approved()
                ->where('public_id', $publicId)
                ->whereHas('service', fn (Builder $q) => $q->where('vendor_profile_id', $vendor->id))
                ->with(['service', 'reviewer', 'vendorResponse'])
                ->first(),
            ReviewType::Vendor => VendorReview::query()
                ->approved()
                ->where('public_id', $publicId)
                ->where('vendor_profile_id', $vendor->id)
                ->with(['reviewer', 'vendorResponse'])
                ->first(),
        };

        if ($review === null) {
            throw new NotFoundHttpException('review_not_found');
        }

        return ApiResponse::success(new VendorReviewInboxResource($review));
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
