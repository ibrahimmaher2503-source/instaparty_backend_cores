<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;

final class ChangeRequestNotificationTemplateSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050401);

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
                'event_key' => 'vendor.changes_requested',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => [
                    'en' => 'Changes requested for {{subject_type}}. Please review and resubmit.',
                    'ar' => 'تم طلب تغييرات على {{subject_type}}. يرجى المراجعة وإعادة التقديم.',
                ],
                'variables' => ['subject_type', 'cycle_number'],
            ],
            [
                'event_key' => 'vendor.changes_requested',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Changes requested - Please resubmit', 'ar' => 'تم طلب تغييرات - يرجى إعادة التقديم'],
                'body' => [
                    'en' => 'We have requested some changes to your {{subject_type}} (Cycle {{cycle_number}}/3). Please review the details and resubmit.',
                    'ar' => 'طلبنا بعض التغييرات على {{subject_type}} (الدورة {{cycle_number}}/3). يرجى مراجعة التفاصيل وإعادة التقديم.',
                ],
                'variables' => ['subject_type', 'cycle_number', 'change_request_link'],
            ],
            [
                'event_key' => 'vendor.resubmitted',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => [
                    'en' => 'Your resubmission for {{subject_type}} has been received.',
                    'ar' => 'تم استلام إعادة التقديم الخاصة بك لـ {{subject_type}}.',
                ],
                'variables' => ['subject_type', 'cycle_number'],
            ],
            [
                'event_key' => 'vendor.resubmitted',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Resubmission received', 'ar' => 'تم استلام إعادة التقديم'],
                'body' => [
                    'en' => 'Thank you for resubmitting your {{subject_type}} (Cycle {{cycle_number}}/3). We are reviewing your updates.',
                    'ar' => 'شكراً لإعادة التقديم {{subject_type}} (الدورة {{cycle_number}}/3). نحن في صدد مراجعة التحديثات الخاصة بك.',
                ],
                'variables' => ['subject_type', 'cycle_number'],
            ],
        ];
    }
}
