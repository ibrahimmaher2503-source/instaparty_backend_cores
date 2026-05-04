<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Communication\Domain\Models\CampaignRun;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CommunicationDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050309);

        DB::transaction(function (): void {
            app(NotificationTemplateSeeder::class)->run();

            $users = User::query()
                ->whereIn('email', [
                    'customer.one@instaparty.local',
                    'customer.two@instaparty.local',
                    'vendor.rental@instaparty.local',
                    'admin@instaparty.local',
                ])
                ->get()
                ->keyBy('email');

            $this->seedPreferences($users);
            $dispatch = $this->seedDispatches($users);
            $this->seedCampaign($users, $dispatch);
        });
    }

    /**
     * @param  Collection<string, User>  $users
     */
    private function seedPreferences($users): void
    {
        $rows = [
            ['email' => 'customer.one@instaparty.local', 'channel' => NotificationChannel::Push, 'category' => EventCategory::Booking, 'enabled' => true],
            ['email' => 'customer.one@instaparty.local', 'channel' => NotificationChannel::Email, 'category' => EventCategory::Marketing, 'enabled' => false],
            ['email' => 'customer.two@instaparty.local', 'channel' => NotificationChannel::Whatsapp, 'category' => EventCategory::Payment, 'enabled' => true],
            ['email' => 'vendor.rental@instaparty.local', 'channel' => NotificationChannel::Email, 'category' => EventCategory::Booking, 'enabled' => true],
        ];

        foreach ($rows as $row) {
            $user = $users->get($row['email']);

            if (! $user instanceof User) {
                continue;
            }

            $this->updateOrCreateFactoryModel(
                NotificationPreference::factory()->make([
                    'public_id' => $this->stablePublicId('notification-preference:'.$user->email.':'.$row['channel']->value.':'.$row['category']->value),
                    'user_id' => $user->id,
                    'channel' => $row['channel'],
                    'event_category' => $row['category'],
                    'is_enabled' => $row['enabled'],
                    'quiet_hours_start' => $row['enabled'] ? '23:00:00' : null,
                    'quiet_hours_end' => $row['enabled'] ? '08:00:00' : null,
                    'timezone' => 'Africa/Cairo',
                ]),
                ['user_id' => $user->id, 'channel' => $row['channel']->value, 'event_category' => $row['category']->value],
            );
        }
    }

    /**
     * @param  Collection<string, User>  $users
     */
    private function seedDispatches($users): NotificationDispatch
    {
        $booking = Booking::query()->where('reference_no', 'BK-DEV-1001')->firstOrFail();
        $customer = $users->get('customer.one@instaparty.local');
        $vendor = $users->get('vendor.rental@instaparty.local');

        if (! $customer instanceof User) {
            $customer = User::query()->where('email', 'customer.one@instaparty.local')->firstOrFail();
        }

        $template = NotificationTemplate::query()
            ->where('event_key', 'booking.confirmed')
            ->where('channel', NotificationChannel::Push->value)
            ->where('audience', NotificationAudience::Customer->value)
            ->firstOrFail();

        /** @var NotificationDispatch $delivered */
        $delivered = $this->firstOrCreateFactoryModel(
            NotificationDispatch::factory()->delivered()->make([
                'public_id' => $this->stablePublicId('notification-dispatch:booking-confirmed:'.$booking->reference_no),
                'notification_template_id' => $template->id,
                'user_id' => $customer->id,
                'channel' => NotificationChannel::Push,
                'locale' => 'ar',
                'status' => DispatchStatus::Delivered,
                'context' => ['booking_reference' => $booking->reference_no, 'vendor_name' => 'Joy Rentals Cairo'],
                'provider' => 'firebase',
                'provider_ref' => 'seed-fcm-1001',
                'reference_type' => Booking::class,
                'reference_id' => $booking->id,
                'sent_at' => '2026-05-16 11:05:00',
                'delivered_at' => '2026-05-16 11:05:05',
                'created_at' => '2026-05-16 11:04:55',
            ]),
            ['public_id' => $this->stablePublicId('notification-dispatch:booking-confirmed:'.$booking->reference_no)],
        );

        if ($vendor instanceof User) {
            $vendorTemplate = NotificationTemplate::query()
                ->where('event_key', 'booking.submitted')
                ->where('channel', NotificationChannel::Email->value)
                ->where('audience', NotificationAudience::Vendor->value)
                ->firstOrFail();

            $this->firstOrCreateFactoryModel(
                NotificationDispatch::factory()->failed()->make([
                    'public_id' => $this->stablePublicId('notification-dispatch:vendor-booking-email:'.$booking->reference_no),
                    'notification_template_id' => $vendorTemplate->id,
                    'user_id' => $vendor->id,
                    'channel' => NotificationChannel::Email,
                    'locale' => 'en',
                    'status' => DispatchStatus::Failed,
                    'context' => ['booking_reference' => $booking->reference_no],
                    'provider' => 'mail',
                    'provider_ref' => null,
                    'reference_type' => Booking::class,
                    'reference_id' => $booking->id,
                    'error_message' => 'Seeded delivery failure for admin testing.',
                    'created_at' => '2026-05-16 11:06:00',
                ]),
                ['public_id' => $this->stablePublicId('notification-dispatch:vendor-booking-email:'.$booking->reference_no)],
            );
        }

        return $delivered;
    }

    /**
     * @param  Collection<string, User>  $users
     */
    private function seedCampaign($users, NotificationDispatch $dispatch): void
    {
        $admin = $users->get('admin@instaparty.local');
        $customerOne = $users->get('customer.one@instaparty.local');
        $customerTwo = $users->get('customer.two@instaparty.local');

        if (! $admin instanceof User) {
            $admin = User::query()->where('email', 'admin@instaparty.local')->firstOrFail();
        }

        /** @var Campaign $campaign */
        $campaign = $this->updateOrCreateFactoryModel(
            Campaign::factory()->scheduled()->make([
                'public_id' => $this->stablePublicId('campaign:may-party-reminder'),
                'name' => 'May party reminder',
                'channel' => CampaignChannel::Push,
                'target_locale' => CampaignTargetLocale::Both,
                'segment_filters' => ['roles' => ['customer'], 'city_codes' => ['EG-C-C-NASR']],
                'product_type_segment' => ['rental', 'sale'],
                'subject' => ['en' => 'Complete your party plan', 'ar' => 'أكمل خطة حفلتك'],
                'body' => [
                    'en' => 'Book rentals and cakes before your event date.',
                    'ar' => 'احجز الإيجارات والكيك قبل موعد مناسبتك.',
                ],
                'scheduled_at' => '2026-05-24 09:00:00',
                'status' => CampaignStatus::Scheduled,
                'created_by' => $admin->id,
            ]),
            ['public_id' => $this->stablePublicId('campaign:may-party-reminder')],
        );

        /** @var CampaignRun $run */
        $run = $this->updateOrCreateFactoryModel(
            CampaignRun::factory()->make([
                'campaign_id' => $campaign->id,
                'started_at' => '2026-05-24 09:00:00',
                'completed_at' => '2026-05-24 09:03:00',
                'recipients_total' => 2,
                'recipients_sent' => 1,
                'recipients_failed' => 1,
            ]),
            ['campaign_id' => $campaign->id, 'started_at' => '2026-05-24 09:00:00'],
        );

        if ($customerOne instanceof User) {
            $this->firstOrCreateFactoryModel(
                CampaignRecipient::factory()->sent()->make([
                    'campaign_run_id' => $run->id,
                    'user_id' => $customerOne->id,
                    'dispatch_id' => $dispatch->id,
                    'status' => CampaignRecipientStatus::Sent,
                    'created_at' => '2026-05-24 09:00:05',
                ]),
                ['campaign_run_id' => $run->id, 'user_id' => $customerOne->id],
            );
        }

        if ($customerTwo instanceof User) {
            $this->firstOrCreateFactoryModel(
                CampaignRecipient::factory()->failed()->make([
                    'campaign_run_id' => $run->id,
                    'user_id' => $customerTwo->id,
                    'dispatch_id' => null,
                    'status' => CampaignRecipientStatus::Failed,
                    'created_at' => '2026-05-24 09:00:06',
                ]),
                ['campaign_run_id' => $run->id, 'user_id' => $customerTwo->id],
            );
        }
    }
}
