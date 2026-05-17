<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;

final class NotificationTemplateSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050308);

        foreach ($this->templates() as $template) {
            $this->updateOrCreateFactoryModel(
                NotificationTemplate::factory()->make([
                    'public_id' => $this->stablePublicId('notification-template:'.$template['event_key'].':'.$template['channel']->value.':'.$template['audience']->value),
                    'event_key' => $template['event_key'],
                    'channel' => $template['channel'],
                    'audience' => $template['audience'],
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'variables' => $template['variables'],
                    'is_active' => true,
                ]),
                [
                    'event_key' => $template['event_key'],
                    'channel' => $template['channel']->value,
                    'audience' => $template['audience']->value,
                ],
            );
        }
    }

    /**
     * @return array<int, array{
     *     event_key: string,
     *     channel: NotificationChannel,
     *     audience: NotificationAudience,
     *     subject: array<string, string|null>,
     *     body: array<string, string>,
     *     variables: array<int, string>
     * }>
     */
    private function templates(): array
    {
        return [
            [
                'event_key' => 'booking.submitted',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => [
                    'en' => 'Your booking has been submitted. We will confirm it shortly.',
                    'ar' => 'تم تقديم حجزك. سنؤكده قريبا.',
                ],
                'variables' => ['booking_id', 'vendor_name'],
            ],
            [
                'event_key' => 'booking.submitted',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'New booking request', 'ar' => 'طلب حجز جديد'],
                'body' => [
                    'en' => 'New booking request #{{booking_id}} from {{customer_name}}.',
                    'ar' => 'طلب حجز جديد رقم #{{booking_id}} من {{customer_name}}.',
                ],
                'variables' => ['booking_id', 'customer_name'],
            ],
            [
                'event_key' => 'booking.confirmed',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => [
                    'en' => 'Your booking with {{vendor_name}} has been confirmed.',
                    'ar' => 'تم تأكيد حجزك مع {{vendor_name}}.',
                ],
                'variables' => ['booking_id', 'vendor_name', 'event_date'],
            ],
            [
                'event_key' => 'payment.captured',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Payment confirmed', 'ar' => 'تم تأكيد الدفع'],
                'body' => [
                    'en' => 'Payment of {{amount}} was confirmed for booking #{{booking_id}}.',
                    'ar' => 'تم تأكيد دفع {{amount}} للحجز رقم #{{booking_id}}.',
                ],
                'variables' => ['amount', 'booking_id'],
            ],
            [
                'event_key' => 'digital.delivered',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => [
                    'en' => 'Your digital product is ready.',
                    'ar' => 'منتجك الرقمي جاهز.',
                ],
                'variables' => ['booking_id', 'redemption_url'],
            ],
            [
                'event_key' => 'review.requested',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Rate your experience', 'ar' => 'قيم تجربتك'],
                'body' => [
                    'en' => 'Tell us how your booking with {{vendor_name}} went.',
                    'ar' => 'أخبرنا كيف كانت تجربتك مع {{vendor_name}}.',
                ],
                'variables' => ['booking_id', 'vendor_name'],
            ],
            [
                'event_key' => 'reconciliation_finding_raised',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Admin,
                'subject' => ['en' => 'High-severity reconciliation finding', 'ar' => 'نتيجة مطابقة بخطورة عالية'],
                'body' => [
                    'en' => 'Reconciliation run {{run_public_id}} raised a high-severity finding: {{finding_type}} on resource {{resource_type}}#{{resource_id}}. Manual review required.',
                    'ar' => 'دورة المطابقة {{run_public_id}} كشفت نتيجة عالية الخطورة: {{finding_type}} على {{resource_type}}#{{resource_id}}. مطلوب مراجعة يدوية.',
                ],
                'variables' => ['run_public_id', 'finding_type', 'resource_type', 'resource_id'],
            ],
            [
                'event_key' => 'reconciliation_finding_raised',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Admin,
                'subject' => ['en' => '[ACTION REQUIRED] High-severity reconciliation finding', 'ar' => '[إجراء مطلوب] نتيجة مطابقة بخطورة عالية'],
                'body' => [
                    'en' => "Reconciliation run {{run_public_id}} raised a high-severity finding.\n\nFinding Type: {{finding_type}}\nResource: {{resource_type}}#{{resource_id}}\n\nPlease review this finding in the admin panel.",
                    'ar' => "دورة المطابقة {{run_public_id}} كشفت نتيجة عالية الخطورة.\n\nنوع النتيجة: {{finding_type}}\nالمورد: {{resource_type}}#{{resource_id}}\n\nيرجى مراجعة هذه النتيجة في لوحة الإدارة.",
                ],
                'variables' => ['run_public_id', 'finding_type', 'resource_type', 'resource_id'],
            ],
        ];
    }
}
