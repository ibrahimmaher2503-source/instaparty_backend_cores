<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers;

use App\Modules\Booking\Application\Actions\ForceCancelBookingAction;
use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Http\Requests\ForceCancelBookingRequest;
use App\Modules\Booking\Http\Resources\BookingAdminInterventionResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BookingInterventionController
{
    public function __construct(
        private readonly ForceCancelBookingAction $forceCancelAction,
    ) {}

    public function forceCancel(ForceCancelBookingRequest $request, string $bookingPublicId): JsonResponse
    {
        $booking = Booking::where('public_id', $bookingPublicId)->firstOrFail();

        Gate::authorize('force_cancel_booking');

        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey) {
            $cached = DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->where('expires_at', '>', now())
                ->first();

            if ($cached) {
                return response()->json(json_decode($cached->response_body, true), 200);
            }
        }

        $intervention = $this->forceCancelAction->execute(
            $booking,
            new AdminInterventionDTO(
                bookingId: $booking->id,
                adminId: auth()->id(),
                interventionType: InterventionType::ForceCancel,
                reason: $request->input('reason'),
            ),
        );

        $resource = new BookingAdminInterventionResource($intervention->load('booking'));
        $responseBody = ApiResponse::success($resource)->getContent();

        if ($idempotencyKey) {
            DB::table('idempotency_keys')->insert([
                'key'             => $idempotencyKey,
                'user_id'         => auth()->id(),
                'route'           => 'admin.bookings.force-cancel',
                'request_hash'    => hash('sha256', $idempotencyKey),
                'response_status' => 200,
                'response_body'   => $responseBody,
                'expires_at'      => now()->addHours(24),
                'created_at'      => now(),
            ]);
        }

        return ApiResponse::success($resource);
    }
}
