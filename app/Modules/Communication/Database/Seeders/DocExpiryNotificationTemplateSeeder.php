<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;

final class DocExpiryNotificationTemplateSeeder extends Seeder
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
                'event_key' => 'vendor.doc_expiring_30d',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Document expiring soon', 'ar' => 'وثيقة قاب قوسين أو أدنى من انتهاء صلاحيتها'],
                'body' => [
                    'en' => 'Your {{doc_type}} will expire in 30 days ({{expiry_date}}). Please renew it to maintain your account status.',
                    'ar' => 'ستنتهي صلاحية {{doc_type}} الخاصة بك خلال 30 يوماً ({{expiry_date}}). يرجى تجديدها للحفاظ على حالة حسابك.',
                ],
                'variables' => ['doc_type', 'expiry_date', 'vendor_name'],
            ],
            [
                'event_key' => 'vendor.doc_expiring_30d',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Document expiring soon', 'ar' => 'وثيقة قاب قوسين أو أدنى من انتهاء صلاحيتها'],
                'body' => [
                    'en' => 'Your {{doc_type}} will expire in 30 days ({{expiry_date}}). Please log in and renew it to maintain your account status.',
                    'ar' => 'ستنتهي صلاحية {{doc_type}} الخاصة بك خلال 30 يوماً ({{expiry_date}}). يرجى تسجيل الدخول وتجديدها للحفاظ على حالة حسابك.',
                ],
                'variables' => ['doc_type', 'expiry_date', 'vendor_name'],
            ],
            [
                'event_key' => 'vendor.doc_expiring_14d',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Urgent: Document expiring in 2 weeks', 'ar' => 'عاجل: وثيقة قاب قوسين أو أدنى من انتهاء الصلاحية خلال أسبوعين'],
                'body' => [
                    'en' => 'Your {{doc_type}} will expire in 14 days ({{expiry_date}}). Renew it now to avoid account suspension.',
                    'ar' => 'ستنتهي صلاحية {{doc_type}} الخاصة بك خلال 14 يوماً ({{expiry_date}}). جددها الآن لتجنب تعليق حسابك.',
                ],
                'variables' => ['doc_type', 'expiry_date', 'vendor_name'],
            ],
            [
                'event_key' => 'vendor.doc_expiring_14d',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Urgent: Document expiring in 2 weeks', 'ar' => 'عاجل: وثيقة قاب قوسين أو أدنى من انتهاء الصلاحية خلال أسبوعين'],
                'body' => [
                    'en' => 'Your {{doc_type}} will expire in 14 days ({{expiry_date}}). Renew it now to avoid account suspension.',
                    'ar' => 'ستنتهي صلاحية {{doc_type}} الخاصة بك خلال 14 يوماً ({{expiry_date}}). جددها الآن لتجنب تعليق حسابك.',
                ],
                'variables' => ['doc_type', 'expiry_date', 'vendor_name'],
            ],
            [
                'event_key' => 'vendor.doc_expiring_7d',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Critical: Document expires in 1 week', 'ar' => 'حرج: وثيقة قاب قوسين أو أدنى من انتهاء الصلاحية خلال أسبوع واحد'],
                'body' => [
                    'en' => 'Your {{doc_type}} expires in 7 days ({{expiry_date}}). Renew immediately or your account will be suspended.',
                    'ar' => 'تنتهي صلاحية {{doc_type}} الخاصة بك في 7 أيام ({{expiry_date}}). جدد فوراً أو سيتم تعليق حسابك.',
                ],
                'variables' => ['doc_type', 'expiry_date', 'vendor_name'],
            ],
            [
                'event_key' => 'vendor.doc_expiring_7d',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Critical: Document expires in 1 week', 'ar' => 'حرج: وثيقة قاب قوسين أو أدنى من انتهاء الصلاحية خلال أسبوع واحد'],
                'body' => [
                    'en' => 'Your {{doc_type}} expires in 7 days ({{expiry_date}}). Renew immediately or your account will be suspended.',
                    'ar' => 'تنتهي صلاحية {{doc_type}} الخاصة بك في 7 أيام ({{expiry_date}}). جدد فوراً أو سيتم تعليق حسابك.',
                ],
                'variables' => ['doc_type', 'expiry_date', 'vendor_name'],
            ],
            [
                'event_key' => 'vendor.doc_expiring_1d',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Final notice: Document expires tomorrow', 'ar' => 'إشعار أخير: وثيقة قاب قوسين أو أدنى من انتهاء الصلاحية غداً'],
                'body' => [
                    'en' => 'Your {{doc_type}} expires tomorrow ({{expiry_date}}). Renew now to keep your account active.',
                    'ar' => 'تنتهي صلاحية {{doc_type}} الخاصة بك غداً ({{expiry_date}}). جدد الآن للحفاظ على حسابك نشطاً.',
                ],
                'variables' => ['doc_type', 'expiry_date', 'vendor_name'],
            ],
            [
                'event_key' => 'vendor.doc_expiring_1d',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Final notice: Document expires tomorrow', 'ar' => 'إشعار أخير: وثيقة قاب قوسين أو أدنى من انتهاء الصلاحية غداً'],
                'body' => [
                    'en' => 'Your {{doc_type}} expires tomorrow ({{expiry_date}}). Renew now to keep your account active.',
                    'ar' => 'تنتهي صلاحية {{doc_type}} الخاصة بك غداً ({{expiry_date}}). جدد الآن للحفاظ على حسابك نشطاً.',
                ],
                'variables' => ['doc_type', 'expiry_date', 'vendor_name'],
            ],
            [
                'event_key' => 'vendor.doc_expired',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Account suspended: Document expired', 'ar' => 'تم تعليق الحساب: وثيقة منتهية الصلاحية'],
                'body' => [
                    'en' => 'Your {{doc_type}} has expired ({{expiry_date}}). Your account has been automatically suspended. Please renew the document and contact support to restore your account.',
                    'ar' => 'انتهت صلاحية {{doc_type}} الخاصة بك ({{expiry_date}}). تم تعليق حسابك تلقائياً. يرجى تجديد الوثيقة والاتصال بالدعم لاستعادة حسابك.',
                ],
                'variables' => ['doc_type', 'expiry_date', 'vendor_name'],
            ],
            [
                'event_key' => 'vendor.doc_expired',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Account suspended: Document expired', 'ar' => 'تم تعليق الحساب: وثيقة منتهية الصلاحية'],
                'body' => [
                    'en' => 'Your {{doc_type}} has expired ({{expiry_date}}). Your account has been automatically suspended. Please log in, renew the document, and contact support to restore your account.',
                    'ar' => 'انتهت صلاحية {{doc_type}} الخاصة بك ({{expiry_date}}). تم تعليق حسابك تلقائياً. يرجى تسجيل الدخول وتجديد الوثيقة والاتصال بالدعم لاستعادة حسابك.',
                ],
                'variables' => ['doc_type', 'expiry_date', 'vendor_name'],
            ],
        ];
    }
}
