<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Database\Seeders;

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewLocale;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewModerationLog;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ReviewsDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050312);

        DB::transaction(function (): void {
            $customer = User::query()->where('email', 'customer.one@instaparty.local')->firstOrFail();
            $admin = User::query()->where('email', 'admin@instaparty.local')->firstOrFail();

            $this->seedServiceReviews($customer, $admin);
            $this->seedVendorReviews($customer, $admin);
        });
    }

    private function seedServiceReviews(User $customer, User $admin): void
    {
        BookingItem::query()
            ->whereHas('bookingVendor.booking', fn ($query) => $query->where('reference_no', 'BK-DEV-1001'))
            ->with('bookingVendor')
            ->get()
            ->each(function (BookingItem $item, int $index) use ($customer, $admin): void {
                $status = $index === 2 ? ModerationStatus::Pending : ModerationStatus::Approved;

                /** @var ServiceReview $review */
                $review = $this->updateOrCreateFactoryModel(
                    ServiceReview::factory()->make([
                        'public_id' => $this->stablePublicId('service-review:booking-item:'.$item->public_id),
                        'service_id' => $item->service_id,
                        'booking_item_id' => $item->id,
                        'user_id' => $customer->id,
                        'rating' => $index === 1 ? 4 : 5,
                        'body' => $index === 2
                            ? 'The digital files arrived quickly and worked well.'
                            : 'Excellent service and smooth setup.',
                        'locale' => $index === 1 ? ReviewLocale::Ar : ReviewLocale::En,
                        'moderation_status' => $status,
                        'moderated_by' => $status === ModerationStatus::Approved ? $admin->id : null,
                        'moderated_at' => $status === ModerationStatus::Approved ? '2026-05-22 11:00:00' : null,
                    ]),
                    ['booking_item_id' => $item->id],
                );

                if ($status === ModerationStatus::Approved) {
                    $this->seedResponse(ReviewType::Service, $review->id, $item->bookingVendor->vendor_profile_id);
                    $this->seedModerationLog(ReviewType::Service, $review->id, $admin, ModerationStatus::Approved);
                }
            });
    }

    private function seedVendorReviews(User $customer, User $admin): void
    {
        BookingVendor::query()
            ->whereHas('booking', fn ($query) => $query->where('reference_no', 'BK-DEV-1001'))
            ->get()
            ->each(function (BookingVendor $bookingVendor, int $index) use ($customer, $admin): void {
                $status = $index === 1 ? ModerationStatus::Approved : ModerationStatus::Pending;

                /** @var VendorReview $review */
                $review = $this->updateOrCreateFactoryModel(
                    VendorReview::factory()->make([
                        'public_id' => $this->stablePublicId('vendor-review:booking-vendor:'.$bookingVendor->public_id),
                        'vendor_profile_id' => $bookingVendor->vendor_profile_id,
                        'booking_vendor_id' => $bookingVendor->id,
                        'user_id' => $customer->id,
                        'rating' => $index === 0 ? 5 : 4,
                        'body' => $index === 1
                            ? 'تعامل محترف وتنسيق جيد.'
                            : 'Vendor was responsive and on time.',
                        'locale' => $index === 1 ? ReviewLocale::Ar : ReviewLocale::En,
                        'moderation_status' => $status,
                        'moderated_by' => $status === ModerationStatus::Approved ? $admin->id : null,
                        'moderated_at' => $status === ModerationStatus::Approved ? '2026-05-22 11:15:00' : null,
                    ]),
                    ['booking_vendor_id' => $bookingVendor->id],
                );

                if ($status === ModerationStatus::Approved) {
                    $this->seedResponse(ReviewType::Vendor, $review->id, $bookingVendor->vendor_profile_id);
                    $this->seedModerationLog(ReviewType::Vendor, $review->id, $admin, ModerationStatus::Approved);
                }
            });
    }

    private function seedResponse(ReviewType $type, int $reviewId, int $vendorId): void
    {
        $this->updateOrCreateFactoryModel(
            ReviewResponse::factory()->approved()->make([
                'review_type' => $type,
                'review_id' => $reviewId,
                'vendor_profile_id' => $vendorId,
                'body' => $type === ReviewType::Service
                    ? 'Thank you for trusting our team.'
                    : 'We appreciate your feedback and look forward to serving you again.',
                'locale' => ReviewLocale::En,
                'moderation_status' => ModerationStatus::Approved,
            ]),
            ['review_type' => $type->value, 'review_id' => $reviewId],
        );
    }

    private function seedModerationLog(ReviewType $type, int $reviewId, User $admin, ModerationStatus $toStatus): void
    {
        $this->firstOrCreateFactoryModel(
            ReviewModerationLog::factory()->make([
                'review_type' => $type->value,
                'review_id' => $reviewId,
                'from_status' => ModerationStatus::Pending->value,
                'to_status' => $toStatus->value,
                'moderator_id' => $admin->id,
                'reason' => ['en' => 'Approved during development seeding.', 'ar' => 'تمت الموافقة أثناء تجهيز بيانات التطوير.'],
                'created_at' => '2026-05-22 11:20:00',
            ]),
            ['review_type' => $type->value, 'review_id' => $reviewId, 'to_status' => $toStatus->value],
        );
    }
}
