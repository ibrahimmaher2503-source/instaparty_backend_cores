<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Customer;

use App\Modules\Booking\Application\Actions\CreateBookingDraftAction;
use App\Modules\Booking\Application\Actions\DiscardDraftBookingAction;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\ValueObjects\BookingCursor;
use App\Modules\Booking\Http\Requests\CreateBookingDraftRequest;
use App\Modules\Booking\Http\Resources\BookingResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Customer - Bookings
 */
class BookingController
{
    private const PAGE_SIZE = 20;

    /** 10.1 — the customer's latest open draft ("current cart"), 404 if none. */
    public function currentDraft(): JsonResponse
    {
        $draft = Booking::query()
            ->where('customer_id', auth()->id())
            ->whereState('lifecycle_status', DraftState::class)
            ->latest('id')
            ->first();

        abort_if($draft === null, 404, 'No open draft');

        $draft->load(['vendors.items', 'vendors.vendor', 'address']);

        return ApiResponse::success(new BookingResource($draft));
    }

    /** 10.8 — discard the draft entirely ("clear cart"). */
    public function discardDraft(string $bookingPublicId, DiscardDraftBookingAction $action): JsonResponse
    {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        abort_if($booking === null || $booking->customer_id !== auth()->id(), 404, 'Not found');

        $action->execute($booking, (int) auth()->id());

        return ApiResponse::success(null);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', Rule::in(['upcoming', 'active', 'completed', 'cancelled'])],
            'cursor' => ['nullable', 'string', 'max:512'],
        ]);

        $query = Booking::query()
            ->where('customer_id', auth()->id())
            ->with(['vendors.items', 'address']);

        if ($request->filled('status')) {
            // Friendly customer-facing buckets → underlying lifecycle states.
            // 'upcoming' has no matching state value; it spans the
            // post-submission, pre-event states. Applying the raw value
            // returned an always-empty list (review finding).
            $states = match ($request->string('status')->toString()) {
                'upcoming' => ['submitted', 'vendor_review', 'customer_review', 'confirmed'],
                'active' => ['active'],
                'completed' => ['completed'],
                'cancelled' => ['cancelled'],
                default => [],
            };
            $query->whereIn('lifecycle_status', $states);
        }

        if ($request->filled('cursor')) {
            $cursor = BookingCursor::decode($request->string('cursor')->toString());
            $query->where(function ($q) use ($cursor): void {
                $q->where('created_at', '<', $cursor->createdAt)
                    ->orWhere(function ($q2) use ($cursor): void {
                        $q2->where('created_at', $cursor->createdAt)
                            ->where('id', '<', $cursor->id);
                    });
            });
        }

        $rows = $query->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        $hasMore = $rows->count() > self::PAGE_SIZE;
        $page = $rows->take(self::PAGE_SIZE);

        $nextCursor = $hasMore
            ? BookingCursor::encode($page->last()->created_at, $page->last()->id)
            : null;

        return ApiResponse::success(
            BookingResource::collection($page),
            ['next_cursor' => $nextCursor],
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

        $booking->load(['vendors.items', 'vendors.vendor', 'address']);

        return ApiResponse::success(new BookingResource($booking));
    }
}
