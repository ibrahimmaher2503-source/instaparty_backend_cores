<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            [
                'event_key' => 'booking.vendor.reminder',
                'channel'   => 'in_app',
                'audience'  => 'vendor',
                'subject'   => json_encode(['en' => 'Booking Response Reminder', 'ar' => 'تذكير بالرد على الحجز']),
                'body'      => json_encode(['en' => 'You have a pending booking that requires your response. Please respond within the deadline.', 'ar' => 'لديك حجز معلق يتطلب ردك. يرجى الرد قبل انتهاء المهلة.']),
            ],
            [
                'event_key' => 'booking.vendor.reminder',
                'channel'   => 'push',
                'audience'  => 'vendor',
                'subject'   => json_encode(['en' => 'Booking Response Reminder', 'ar' => 'تذكير بالرد على الحجز']),
                'body'      => json_encode(['en' => 'You have a pending booking that requires your response. Please respond within the deadline.', 'ar' => 'لديك حجز معلق يتطلب ردك. يرجى الرد قبل انتهاء المهلة.']),
            ],
            [
                'event_key' => 'booking.vendor.timed_out_by_admin',
                'channel'   => 'in_app',
                'audience'  => 'vendor',
                'subject'   => json_encode(['en' => 'Booking Assignment Removed', 'ar' => 'تم إلغاء تعيين الحجز']),
                'body'      => json_encode(['en' => 'Your booking assignment has been removed by an admin due to non-response.', 'ar' => 'تم إلغاء تعيينك للحجز من قِبل المشرف بسبب عدم الرد.']),
            ],
            [
                'event_key' => 'booking.vendor.timed_out_by_admin',
                'channel'   => 'push',
                'audience'  => 'vendor',
                'subject'   => json_encode(['en' => 'Booking Assignment Removed', 'ar' => 'تم إلغاء تعيين الحجز']),
                'body'      => json_encode(['en' => 'Your booking assignment has been removed by an admin due to non-response.', 'ar' => 'تم إلغاء تعيينك للحجز من قِبل المشرف بسبب عدم الرد.']),
            ],
            [
                'event_key' => 'booking.vendor.timed_out_by_admin.customer',
                'channel'   => 'in_app',
                'audience'  => 'customer',
                'subject'   => json_encode(['en' => 'Vendor Could Not Be Reached', 'ar' => 'تعذّر الوصول إلى مزود الخدمة']),
                'body'      => json_encode(['en' => 'The vendor could not be reached in time. We are working to find you an alternative.', 'ar' => 'تعذّر الوصول إلى مزود الخدمة في الوقت المحدد. نعمل على إيجاد بديل لك.']),
            ],
            [
                'event_key' => 'booking.vendor.timed_out_by_admin.customer',
                'channel'   => 'push',
                'audience'  => 'customer',
                'subject'   => json_encode(['en' => 'Vendor Could Not Be Reached', 'ar' => 'تعذّر الوصول إلى مزود الخدمة']),
                'body'      => json_encode(['en' => 'The vendor could not be reached in time. We are working to find you an alternative.', 'ar' => 'تعذّر الوصول إلى مزود الخدمة في الوقت المحدد. نعمل على إيجاد بديل لك.']),
            ],
            [
                'event_key' => 'booking.alternatives.suggested',
                'channel'   => 'in_app',
                'audience'  => 'customer',
                'subject'   => json_encode(['en' => 'Alternative Vendors Suggested', 'ar' => 'تم اقتراح مزودين بديلين']),
                'body'      => json_encode(['en' => 'We have found alternative vendors for your booking. Please review the suggestions.', 'ar' => 'لقد وجدنا مزودين بديلين لحجزك. يرجى مراجعة الاقتراحات.']),
            ],
            [
                'event_key' => 'booking.alternatives.suggested',
                'channel'   => 'push',
                'audience'  => 'customer',
                'subject'   => json_encode(['en' => 'Alternative Vendors Suggested', 'ar' => 'تم اقتراح مزودين بديلين']),
                'body'      => json_encode(['en' => 'We have found alternative vendors for your booking. Please review the suggestions.', 'ar' => 'لقد وجدنا مزودين بديلين لحجزك. يرجى مراجعة الاقتراحات.']),
            ],
            [
                'event_key' => 'booking.customer_review.reminder',
                'channel'   => 'in_app',
                'audience'  => 'customer',
                'subject'   => json_encode(['en' => 'Share Your Experience', 'ar' => 'شارك تجربتك']),
                'body'      => json_encode(['en' => 'How was your experience? Please take a moment to leave a review for the service you received.', 'ar' => 'كيف كانت تجربتك؟ يرجى تخصيص لحظة لكتابة تقييم للخدمة التي تلقيتها.']),
            ],
            [
                'event_key' => 'booking.customer_review.reminder',
                'channel'   => 'push',
                'audience'  => 'customer',
                'subject'   => json_encode(['en' => 'Share Your Experience', 'ar' => 'شارك تجربتك']),
                'body'      => json_encode(['en' => 'How was your experience? Please take a moment to leave a review for the service you received.', 'ar' => 'كيف كانت تجربتك؟ يرجى تخصيص لحظة لكتابة تقييم للخدمة التي تلقيتها.']),
            ],
        ];

        $insertRows = array_map(fn (array $row): array => array_merge($row, [
            'public_id'  => Str::ulid()->toBase32(),
            'is_active'  => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $rows);

        DB::table('notification_templates')->insertOrIgnore($insertRows);
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereIn('event_key', [
                'booking.vendor.reminder',
                'booking.vendor.timed_out_by_admin',
                'booking.vendor.timed_out_by_admin.customer',
                'booking.alternatives.suggested',
                'booking.customer_review.reminder',
            ])
            ->delete();
    }
};
