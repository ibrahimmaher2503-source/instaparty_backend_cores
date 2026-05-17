<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Customer;

use App\Modules\Booking\Application\Actions\CreateBookingDraftAction;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Http\Requests\CreateBookingDraftRequest;
use App\Modules\Booking\Http\Resources\BookingResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController
{
    public function index(Request $request): JsonResponse
    {
        $query = Booking::query()
            ->where('customer_id', auth()->id())
            ->with(['vendors.items', 'address'])
            ->orderByDesc('id');

        if ($request->filled('lifecycle_status')) {
            $query->where('lifecycle_status', $request->string('lifecycle_status'));
        }

        $paginator = $query->paginate(perPage: (int) $request->integer('per_page', 15));

        return ApiResponse::success(
            BookingResource::collection($paginator->items()),
            [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateBookingDraftRequest $request): JsonResponse
    {
        $booking = app(CreateBookingDraftAction::class)->execute($request->toDTO());

        return ApiResponse::success(new BookingResource($booking), [], 201);
    }

    public function show(string $publicId): JsonResponse
    {
        $booking = app(BookingRepository::class)->findByPublicId($publicId);

        if ($booking === null || $booking->customer_id !== auth()->id()) {
            return ApiResponse::error('Not found', 404);
        }

        $booking->load(['vendors.items', 'address']);

        return ApiResponse::success(new BookingResource($booking));
    }
}
