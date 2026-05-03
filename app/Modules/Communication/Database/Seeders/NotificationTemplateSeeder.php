<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = $this->coreTemplates();
        $templates = array_merge($templates, $this->perTypeTemplates());

        foreach ($templates as $tpl) {
            DB::table('notification_templates')->updateOrInsert(
                [
                    'event_key' => $tpl['event_key'],
                    'channel' => $tpl['channel'],
                    'audience' => $tpl['audience'],
                ],
                array_merge($tpl, [
                    'public_id' => $tpl['public_id'] ?? Str::ulid()->toBase32(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    private function coreTemplates(): array
    {
        return [
            // booking.submitted — customer
            [
                'event_key' => 'booking.submitted',
                'channel' => 'push',
                'audience' => 'customer',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'Your booking has been submitted. We\'ll confirm it shortly.',
                    'ar' => 'تم تقديم حجزك. سنؤكده قريباً.',
                ]),
                'variables' => json_encode(['booking_id', 'vendor_name']),
            ],
            [
                'event_key' => 'booking.submitted',
                'channel' => 'email',
                'audience' => 'customer',
                'subject' => json_encode(['en' => 'Booking Submitted — InstaParty', 'ar' => 'تم تقديم الحجز — إنستاباتي']),
                'body' => json_encode([
                    'en' => 'Dear {{customer_name}}, your booking #{{booking_id}} has been submitted to {{vendor_name}}. You\'ll hear back within 24 hours.',
                    'ar' => 'عزيزي {{customer_name}}، تم تقديم حجزك رقم #{{booking_id}} إلى {{vendor_name}}. ستتلقى ردًا خلال 24 ساعة.',
                ]),
                'variables' => json_encode(['customer_name', 'booking_id', 'vendor_name']),
            ],
            // booking.submitted — vendor
            [
                'event_key' => 'booking.submitted',
                'channel' => 'push',
                'audience' => 'vendor',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'New booking request from {{customer_name}} for {{event_date}}.',
                    'ar' => 'طلب حجز جديد من {{customer_name}} بتاريخ {{event_date}}.',
                ]),
                'variables' => json_encode(['customer_name', 'event_date', 'booking_id']),
            ],
            [
                'event_key' => 'booking.submitted',
                'channel' => 'sms',
                'audience' => 'vendor',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'InstaParty: New booking request #{{booking_id}} from {{customer_name}}. Reply in 24h to avoid cancellation.',
                    'ar' => 'إنستاباتي: طلب حجز جديد #{{booking_id}} من {{customer_name}}. الرد خلال 24 ساعة.',
                ]),
                'variables' => json_encode(['booking_id', 'customer_name']),
            ],
            // booking.modified — customer
            [
                'event_key' => 'booking.modified',
                'channel' => 'push',
                'audience' => 'customer',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'Your booking has been modified. Please review the changes.',
                    'ar' => 'تم تعديل حجزك. يرجى مراجعة التغييرات.',
                ]),
                'variables' => json_encode(['booking_id']),
            ],
            [
                'event_key' => 'booking.modified',
                'channel' => 'email',
                'audience' => 'customer',
                'subject' => json_encode(['en' => 'Booking Modified — InstaParty', 'ar' => 'تم تعديل الحجز — إنستاباتي']),
                'body' => json_encode([
                    'en' => 'Your booking #{{booking_id}} has been modified. Review the updated details in the app.',
                    'ar' => 'تم تعديل حجزك رقم #{{booking_id}}. راجع التفاصيل المحدّثة في التطبيق.',
                ]),
                'variables' => json_encode(['booking_id']),
            ],
            // booking.confirmed — customer
            [
                'event_key' => 'booking.confirmed',
                'channel' => 'push',
                'audience' => 'customer',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'Great news! Your booking has been confirmed by {{vendor_name}}.',
                    'ar' => 'أخبار رائعة! تم تأكيد حجزك من قِبل {{vendor_name}}.',
                ]),
                'variables' => json_encode(['vendor_name', 'booking_id']),
            ],
            [
                'event_key' => 'booking.confirmed',
                'channel' => 'email',
                'audience' => 'customer',
                'subject' => json_encode(['en' => 'Booking Confirmed — InstaParty', 'ar' => 'تأكيد الحجز — إنستاباتي']),
                'body' => json_encode([
                    'en' => 'Your booking #{{booking_id}} with {{vendor_name}} is confirmed for {{event_date}}.',
                    'ar' => 'تم تأكيد حجزك رقم #{{booking_id}} مع {{vendor_name}} بتاريخ {{event_date}}.',
                ]),
                'variables' => json_encode(['booking_id', 'vendor_name', 'event_date']),
            ],
            [
                'event_key' => 'booking.confirmed',
                'channel' => 'sms',
                'audience' => 'customer',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'InstaParty: Booking #{{booking_id}} confirmed by {{vendor_name}} for {{event_date}}.',
                    'ar' => 'إنستاباتي: تم تأكيد الحجز #{{booking_id}} من {{vendor_name}} بتاريخ {{event_date}}.',
                ]),
                'variables' => json_encode(['booking_id', 'vendor_name', 'event_date']),
            ],
            // payment.captured — customer
            [
                'event_key' => 'payment.captured',
                'channel' => 'push',
                'audience' => 'customer',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'Payment of {{amount}} confirmed for booking #{{booking_id}}.',
                    'ar' => 'تم تأكيد الدفع {{amount}} للحجز #{{booking_id}}.',
                ]),
                'variables' => json_encode(['amount', 'booking_id']),
            ],
            [
                'event_key' => 'payment.captured',
                'channel' => 'email',
                'audience' => 'customer',
                'subject' => json_encode(['en' => 'Payment Confirmed — InstaParty', 'ar' => 'تأكيد الدفع — إنستاباتي']),
                'body' => json_encode([
                    'en' => 'Your payment of {{amount}} for booking #{{booking_id}} with {{vendor_name}} has been captured.',
                    'ar' => 'تم استلام دفعتك {{amount}} للحجز #{{booking_id}} مع {{vendor_name}}.',
                ]),
                'variables' => json_encode(['amount', 'booking_id', 'vendor_name']),
            ],
            // payment.captured — vendor
            [
                'event_key' => 'payment.captured',
                'channel' => 'push',
                'audience' => 'vendor',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'Payment received for booking #{{booking_id}}. Your settlement will be processed after the event.',
                    'ar' => 'تم استلام الدفع للحجز #{{booking_id}}. سيتم التسوية بعد الفعالية.',
                ]),
                'variables' => json_encode(['booking_id']),
            ],
        ];
    }

    private function perTypeTemplates(): array
    {
        return [
            // rental.delivery_scheduled — customer
            [
                'event_key' => 'rental.delivery_scheduled',
                'channel' => 'push',
                'audience' => 'customer',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'Your rental delivery is scheduled for {{delivery_date}} between {{time_window}}.',
                    'ar' => 'تم جدولة تسليم الإيجار بتاريخ {{delivery_date}} بين {{time_window}}.',
                ]),
                'variables' => json_encode(['delivery_date', 'time_window', 'booking_id']),
            ],
            [
                'event_key' => 'rental.delivery_scheduled',
                'channel' => 'sms',
                'audience' => 'customer',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'InstaParty: Rental delivery for #{{booking_id}} scheduled {{delivery_date}} {{time_window}}.',
                    'ar' => 'إنستاباتي: تسليم الإيجار #{{booking_id}} مجدول {{delivery_date}} {{time_window}}.',
                ]),
                'variables' => json_encode(['booking_id', 'delivery_date', 'time_window']),
            ],
            // sale.preparation_started — customer
            [
                'event_key' => 'sale.preparation_started',
                'channel' => 'push',
                'audience' => 'customer',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => '{{vendor_name}} has started preparing your order for {{event_date}}.',
                    'ar' => 'بدأ {{vendor_name}} في تحضير طلبك لتاريخ {{event_date}}.',
                ]),
                'variables' => json_encode(['vendor_name', 'event_date', 'booking_id']),
            ],
            // digital.delivered — customer
            [
                'event_key' => 'digital.delivered',
                'channel' => 'push',
                'audience' => 'customer',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'Your digital product is ready! Tap to access it.',
                    'ar' => 'منتجك الرقمي جاهز! اضغط للوصول إليه.',
                ]),
                'variables' => json_encode(['booking_id', 'redemption_url']),
            ],
            [
                'event_key' => 'digital.delivered',
                'channel' => 'email',
                'audience' => 'customer',
                'subject' => json_encode(['en' => 'Your Digital Product is Ready — InstaParty', 'ar' => 'منتجك الرقمي جاهز — إنستاباتي']),
                'body' => json_encode([
                    'en' => 'Your digital product for booking #{{booking_id}} is ready. Access it here: {{redemption_url}}',
                    'ar' => 'منتجك الرقمي للحجز #{{booking_id}} جاهز. الوصول إليه هنا: {{redemption_url}}',
                ]),
                'variables' => json_encode(['booking_id', 'redemption_url']),
            ],
            // digital.expiring_soon — customer
            [
                'event_key' => 'digital.expiring_soon',
                'channel' => 'push',
                'audience' => 'customer',
                'subject' => json_encode(['en' => null, 'ar' => null]),
                'body' => json_encode([
                    'en' => 'Your digital product expires on {{expiry_date}}. Use it before it\'s gone!',
                    'ar' => 'منتجك الرقمي ينتهي في {{expiry_date}}. استخدمه قبل انتهاء صلاحيته!',
                ]),
                'variables' => json_encode(['expiry_date', 'booking_id', 'redemption_url']),
            ],
            [
                'event_key' => 'digital.expiring_soon',
                'channel' => 'email',
                'audience' => 'customer',
                'subject' => json_encode(['en' => 'Your Digital Product is Expiring Soon — InstaParty', 'ar' => 'منتجك الرقمي ينتهي قريبًا — إنستاباتي']),
                'body' => json_encode([
                    'en' => 'Heads up! Your digital product for booking #{{booking_id}} expires on {{expiry_date}}.',
                    'ar' => 'تنبيه! منتجك الرقمي للحجز #{{booking_id}} ينتهي بتاريخ {{expiry_date}}.',
                ]),
                'variables' => json_encode(['booking_id', 'expiry_date', 'redemption_url']),
            ],
        ];
    }
}
