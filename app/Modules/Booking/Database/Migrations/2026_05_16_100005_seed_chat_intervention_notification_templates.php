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
                'event_key' => 'booking.chat.frozen',
                'channel'   => 'in_app',
                'audience'  => 'customer',
                'subject'   => json_encode(['en' => 'Booking Chat Frozen', 'ar' => 'تم تجميد محادثة الحجز']),
                'body'      => json_encode(['en' => 'Your booking chat has been frozen by an admin. You can still view messages but cannot send new ones.', 'ar' => 'تم تجميد محادثة حجزك من قِبل المشرف. يمكنك الاطلاع على الرسائل لكنك لن تتمكن من إرسال رسائل جديدة.']),
            ],
            [
                'event_key' => 'booking.chat.frozen',
                'channel'   => 'push',
                'audience'  => 'customer',
                'subject'   => json_encode(['en' => 'Booking Chat Frozen', 'ar' => 'تم تجميد محادثة الحجز']),
                'body'      => json_encode(['en' => 'Your booking chat has been frozen by an admin.', 'ar' => 'تم تجميد محادثة حجزك من قِبل المشرف.']),
            ],
            [
                'event_key' => 'booking.chat.frozen',
                'channel'   => 'in_app',
                'audience'  => 'vendor',
                'subject'   => json_encode(['en' => 'Booking Chat Frozen', 'ar' => 'تم تجميد محادثة الحجز']),
                'body'      => json_encode(['en' => 'The booking chat has been frozen by an admin. No new messages can be sent until it is resumed.', 'ar' => 'تم تجميد محادثة الحجز من قِبل المشرف. لا يمكن إرسال رسائل جديدة حتى يتم استئنافها.']),
            ],
            [
                'event_key' => 'booking.chat.frozen',
                'channel'   => 'push',
                'audience'  => 'vendor',
                'subject'   => json_encode(['en' => 'Booking Chat Frozen', 'ar' => 'تم تجميد محادثة الحجز']),
                'body'      => json_encode(['en' => 'The booking chat has been frozen by an admin.', 'ar' => 'تم تجميد محادثة الحجز من قِبل المشرف.']),
            ],
            [
                'event_key' => 'booking.chat.resumed',
                'channel'   => 'in_app',
                'audience'  => 'customer',
                'subject'   => json_encode(['en' => 'Booking Chat Resumed', 'ar' => 'تم استئناف محادثة الحجز']),
                'body'      => json_encode(['en' => 'Your booking chat has been resumed. You can now send messages again.', 'ar' => 'تم استئناف محادثة حجزك. يمكنك الآن إرسال الرسائل مرة أخرى.']),
            ],
            [
                'event_key' => 'booking.chat.resumed',
                'channel'   => 'push',
                'audience'  => 'customer',
                'subject'   => json_encode(['en' => 'Booking Chat Resumed', 'ar' => 'تم استئناف محادثة الحجز']),
                'body'      => json_encode(['en' => 'Your booking chat has been resumed.', 'ar' => 'تم استئناف محادثة حجزك.']),
            ],
            [
                'event_key' => 'booking.chat.resumed',
                'channel'   => 'in_app',
                'audience'  => 'vendor',
                'subject'   => json_encode(['en' => 'Booking Chat Resumed', 'ar' => 'تم استئناف محادثة الحجز']),
                'body'      => json_encode(['en' => 'The booking chat has been resumed. You can now send messages again.', 'ar' => 'تم استئناف محادثة الحجز. يمكنكم الآن إرسال الرسائل مرة أخرى.']),
            ],
            [
                'event_key' => 'booking.chat.resumed',
                'channel'   => 'push',
                'audience'  => 'vendor',
                'subject'   => json_encode(['en' => 'Booking Chat Resumed', 'ar' => 'تم استئناف محادثة الحجز']),
                'body'      => json_encode(['en' => 'The booking chat has been resumed.', 'ar' => 'تم استئناف محادثة الحجز.']),
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
            ->whereIn('event_key', ['booking.chat.frozen', 'booking.chat.resumed'])
            ->delete();
    }
};
