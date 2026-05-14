<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console\Commands;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Application\Actions\AutoSuspendForExpiredDocAction;
use App\Modules\Identity\Domain\Enums\ComplianceEventType;
use App\Modules\Identity\Domain\Models\VendorComplianceEvent;
use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CheckDocumentExpiryCommand extends Command
{
    protected $signature = 'identity:check-document-expiry';

    protected $description = 'Check for expiring vendor documents and send reminders';

    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
        private readonly AutoSuspendForExpiredDocAction $autoSuspend,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $documents = VendorDocument::withExpiry()->expiringWithinDays(30)->get();

        foreach ($documents as $document) {
            try {
                $this->processDocument($document);
            } catch (\Exception $e) {
                $this->error("Failed to process document {$document->public_id}: {$e->getMessage()}");
            }
        }

        $expiredDocuments = VendorDocument::withExpiry()->expired()->get();

        foreach ($expiredDocuments as $document) {
            try {
                $this->processExpiredDocument($document);
            } catch (\Exception $e) {
                $this->error("Failed to process expired document {$document->public_id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function processDocument(VendorDocument $document): void
    {
        $daysUntilExpiry = (int) today()->diffInDays($document->expires_at);
        $reminderDays = [30, 14, 7, 1];

        // Check if reminder should be sent for this day boundary
        if (! in_array($daysUntilExpiry, $reminderDays)) {
            return;
        }

        // Check if reminder was already sent today
        if ($document->last_reminder_sent_at?->isToday()) {
            return;
        }

        $eventKey = match ($daysUntilExpiry) {
            30 => 'vendor.doc_expiring_30d',
            14 => 'vendor.doc_expiring_14d',
            7 => 'vendor.doc_expiring_7d',
            1 => 'vendor.doc_expiring_1d',
        };

        // Dispatch notification
        $this->dispatchNotification->execute(new DispatchNotificationDTO(
            eventKey: $eventKey,
            channel: NotificationChannel::InApp,
            audience: NotificationAudience::Vendor,
            eventCategory: EventCategory::System,
            userId: $document->vendorProfile->user_id,
            context: [
                'doc_type' => __('identity.document_type.'.$document->doc_type->value),
                'expiry_date' => $document->expires_at->format('Y-m-d'),
                'vendor_name' => $document->vendorProfile->getTranslation('business_name', 'en'),
            ]
        ));

        // Update last_reminder_sent_at
        $document->update(['last_reminder_sent_at' => today()]);

        // Log compliance event
        VendorComplianceEvent::create([
            'public_id' => Str::ulid(),
            'vendor_profile_id' => $document->vendor_profile_id,
            'document_id' => $document->id,
            'event_type' => ComplianceEventType::ReminderSent,
            'occurred_at' => now(),
            'reason' => [
                'en' => "Reminder sent: Document expires in {$daysUntilExpiry} days",
                'ar' => "تم إرسال تذكير: انتهاء صلاحية الوثيقة في {$daysUntilExpiry} أيام",
            ],
        ]);
    }

    private function processExpiredDocument(VendorDocument $document): void
    {
        $vendorProfile = $document->vendorProfile;

        if ($document->is_critical && $vendorProfile->approval_status !== 'suspended') {
            $this->autoSuspend->execute($document);
        } elseif (! $document->is_critical && ! $document->last_reminder_sent_at?->isToday()) {
            $this->dispatchNotification->execute(new DispatchNotificationDTO(
                eventKey: 'vendor.doc_expired',
                channel: NotificationChannel::InApp,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::System,
                userId: $vendorProfile->user_id,
                context: [
                    'doc_type' => __('identity.document_type.'.$document->doc_type->value),
                    'expiry_date' => $document->expires_at->format('Y-m-d'),
                    'vendor_name' => $vendorProfile->getTranslation('business_name', 'en'),
                ]
            ));

            $document->update(['last_reminder_sent_at' => today()]);
        }
    }
}
