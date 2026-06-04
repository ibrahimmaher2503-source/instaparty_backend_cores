<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment;

use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\Exceptions\LaneNotSupportedForTypeException;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * G8 — rental handover/return condition photos.
 *
 * Rental-only by domain rule: sale and digital items have no physical asset
 * whose condition needs documenting.
 */
class UploadConditionPhotosAction
{
    /**
     * @param  list<UploadedFile>  $photos
     * @return list<Media>
     */
    public function execute(BookingItem $item, User $actor, string $phase, array $photos): array
    {
        return DB::transaction(function () use ($item, $actor, $phase, $photos): array {
            match ($item->product_type) {
                ProductType::Rental => null,
                ProductType::Sale, ProductType::Digital => throw new LaneNotSupportedForTypeException(
                    FulfillmentLane::ReportIssue,
                    $item->product_type,
                ),
            };

            $media = [];

            foreach ($photos as $photo) {
                $media[] = $item->addMedia($photo)
                    ->withCustomProperties([
                        'phase' => $phase,
                        'uploaded_by' => $actor->id,
                    ])
                    ->toMediaCollection('condition_photos');
            }

            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => BookingItem::class,
                'auditable_id' => $item->id,
                'user_id' => $actor->id,
                'action' => 'condition_photos_uploaded',
                'changes' => json_encode(['phase' => $phase, 'count' => count($media)]),
                'created_at' => now(),
            ]);

            return $media;
        });
    }
}
