<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Infrastructure\Repositories;

use App\Modules\Reviews\Application\DTOs\SubmitReviewData;
use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Illuminate\Support\Str;

class EloquentVendorReviewRepository implements VendorReviewRepository
{
    public function create(SubmitReviewData $data, int $vendorProfileId, int $bookingVendorId): VendorReview
    {
        return VendorReview::create([
            'public_id' => Str::ulid()->toBase32(),
            'vendor_profile_id' => $vendorProfileId,
            'booking_vendor_id' => $bookingVendorId,
            'user_id' => $data->userId,
            'rating' => $data->rating,
            'body' => $data->body,
            'locale' => $data->locale,
            'moderation_status' => ModerationStatus::Pending->value,
        ]);
    }

    public function findByPublicIdForUser(string $publicId, int $userId): ?VendorReview
    {
        return VendorReview::where('public_id', $publicId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findByBookingVendorId(int $bookingVendorId): ?VendorReview
    {
        return VendorReview::withTrashed()
            ->where('booking_vendor_id', $bookingVendorId)
            ->first();
    }

    public function listApprovedForVendor(int $vendorProfileId, array $options = []): array
    {
        $limit = $options['limit'] ?? 15;
        $cursor = $options['cursor'] ?? null;

        $query = VendorReview::with('reviewer')
            ->where('vendor_profile_id', $vendorProfileId)
            ->where('moderation_status', ModerationStatus::Approved->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($cursor !== null) {
            $decoded = json_decode(base64_decode($cursor), true);
            if (isset($decoded['created_at'], $decoded['id'])) {
                $query->where(function ($q) use ($decoded): void {
                    $q->where('created_at', '<', $decoded['created_at'])
                        ->orWhere(function ($q2) use ($decoded): void {
                            $q2->where('created_at', '=', $decoded['created_at'])
                                ->where('id', '<', $decoded['id']);
                        });
                });
            }
        }

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        $items = $items->take($limit);

        $nextCursor = $hasMore
            ? base64_encode(json_encode(['created_at' => $items->last()->created_at->toISOString(), 'id' => $items->last()->id]))
            : null;

        return [
            'items' => $items->all(),
            'next_cursor' => $nextCursor,
            'prev_cursor' => null,
        ];
    }

    public function aggregateApprovedForVendor(int $vendorProfileId): array
    {
        $result = VendorReview::where('vendor_profile_id', $vendorProfileId)
            ->where('moderation_status', ModerationStatus::Approved->value)
            ->selectRaw('AVG(rating) as average, COUNT(*) as count')
            ->first();

        return [
            'average' => round((float) ($result->average ?? 0), 2),
            'count' => (int) ($result->count ?? 0),
        ];
    }

    public function softDelete(VendorReview $review): void
    {
        $review->delete();
    }

    public function transition(VendorReview $review, string $toStatus, int $moderatorId): void
    {
        $review->update([
            'moderation_status' => $toStatus,
            'moderated_by' => $moderatorId,
            'moderated_at' => now(),
        ]);
    }
}
