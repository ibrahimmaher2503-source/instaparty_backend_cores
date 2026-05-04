<?php

declare(strict_types=1);

// T047 — FR-5.3.18: Campaign dispatch must go through DispatchNotificationAction (Phase 5.0 contract).
// DispatchCampaignRecipientJob must NOT directly reference any channel adapter class.
// All channel routing must remain inside DispatchNotificationAction.

it('DispatchCampaignRecipientJob uses DispatchNotificationAction and not direct adapter classes', function (): void {
    $jobFile = base_path('app/Modules/Communication/Application/Jobs/DispatchCampaignRecipientJob.php');

    expect(file_exists($jobFile))->toBeTrue();

    $code = file_get_contents($jobFile);

    expect($code)->toContain('DispatchNotificationAction');

    foreach (['FcmPushAdapter', 'VonageSmsAdapter', 'WhatsAppStubAdapter', 'MailchimpEmailAdapter'] as $adapter) {
        expect($code)->not->toContain($adapter);
    }
})->group('communication');
