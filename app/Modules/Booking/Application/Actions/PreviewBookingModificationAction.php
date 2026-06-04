<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\VendorModifyDTO;
use App\Modules\Booking\Application\Services\BookingModificationDiffService;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * G6 — preview a booking modification without persisting anything.
 *
 * Runs the same eligibility guards as VendorModifyBookingAction, computes
 * the diff, and parks a one-time preview token in the cache (30-min TTL).
 * NO database writes. NO events. Submitting with the token later proves the
 * vendor saw exactly this diff (hash-bound to the change set).
 */
class PreviewBookingModificationAction
{
    public const TTL_SECONDS = 1800;

    public function __construct(
        private readonly BookingModificationDiffService $diffService,
    ) {}

    /** @return array{diff: array<string,mixed>, preview_token: string, expires_at: string} */
    public function execute(VendorModifyDTO $dto): array
    {
        /** @var BookingVendor $bookingVendor */
        $bookingVendor = BookingVendor::query()->with('items')->where('id', $dto->bookingVendorId)->firstOrFail();

        abort_if(
            $bookingVendor->vendor_profile_id !== $dto->vendorProfileId,
            Response::HTTP_FORBIDDEN
        );

        abort_if(
            $bookingVendor->sub_status !== VendorSubStatus::Pending,
            Response::HTTP_CONFLICT,
            'Booking vendor is not in pending status'
        );

        if ($bookingVendor->response_deadline !== null
            && $bookingVendor->response_deadline->isPast()) {
            throw new ResponseDeadlineExpiredException($bookingVendor->id);
        }

        abort_if(
            BookingModification::where('booking_vendor_id', $bookingVendor->id)
                ->where('status', ModificationStatus::Pending)
                ->exists(),
            Response::HTTP_CONFLICT,
            'A pending modification already exists'
        );

        $diff = $this->diffService->buildDiffSnapshot($bookingVendor, $dto->changes);

        $token = Str::ulid()->toBase32();
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);

        Cache::put(
            self::cacheKey($dto->vendorProfileId, $token),
            [
                'booking_vendor_id' => $bookingVendor->id,
                'changes_hash' => $this->diffService->changesHash($bookingVendor->id, $dto->changes),
            ],
            $expiresAt,
        );

        return [
            'diff' => $diff,
            'preview_token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public static function cacheKey(int $vendorProfileId, string $token): string
    {
        return "modification_preview:{$vendorProfileId}:{$token}";
    }
}
