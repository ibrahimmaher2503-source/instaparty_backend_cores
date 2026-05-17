<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Jobs\DetectSuspiciousMessageJob;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Enums\ChatMessageFlagReason;
use App\Modules\Communication\Domain\Events\ChatMessageFlagged;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);
uses()->group('chat_moderation', 'feature');

function dispatchDetectionSync(int $logId, string $body): void
{
    (new DetectSuspiciousMessageJob($logId, $body))->handle(
        app(\App\Modules\Communication\Infrastructure\Services\MessagePatternDetector::class),
    );
}

it('creates flags for phone Latin, phone Arabic, email, and link messages and leaves innocent untouched', function () {
    Event::fake([ChatMessageFlagged::class]);

    $phoneLatin = ChatMessageLog::factory()->create();
    $phoneArabic = ChatMessageLog::factory()->create();
    $emailLog = ChatMessageLog::factory()->create();
    $linkLog = ChatMessageLog::factory()->create();
    $innocent = ChatMessageLog::factory()->create();

    dispatchDetectionSync($phoneLatin->id, 'Call me 010 1234 5678');
    dispatchDetectionSync($phoneArabic->id, 'اتصل بي ٠١٠١٢٣٤٥٦٧٨');
    dispatchDetectionSync($emailLog->id, 'Email: someone@example.com');
    dispatchDetectionSync($linkLog->id, 'Find me here: https://t.me/instaparty');
    dispatchDetectionSync($innocent->id, 'Looking forward to the party!');

    expect(ChatModerationFlag::count())->toBe(4);

    foreach ([$phoneLatin, $phoneArabic] as $log) {
        $log->refresh();
        expect($log->flagged)->toBeTrue()
            ->and($log->flag_reason)->toBe(ChatMessageFlagReason::PhonePattern->value);
    }

    $emailLog->refresh();
    expect($emailLog->flagged)->toBeTrue()
        ->and($emailLog->flag_reason)->toBe(ChatMessageFlagReason::EmailPattern->value);

    $linkLog->refresh();
    expect($linkLog->flagged)->toBeTrue()
        ->and($linkLog->flag_reason)->toBe(ChatMessageFlagReason::ExternalLink->value);

    $innocent->refresh();
    expect($innocent->flagged)->toBeFalse()
        ->and($innocent->flag_reason)->toBeNull();

    Event::assertDispatched(ChatMessageFlagged::class, 4);
});

it('is idempotent: re-dispatching the same body for an already-flagged log creates no new flag rows and no extra events', function () {
    Event::fake([ChatMessageFlagged::class]);

    $log = ChatMessageLog::factory()->create();
    $body = 'Phone: 01012345678';

    dispatchDetectionSync($log->id, $body);

    expect(ChatModerationFlag::where('chat_message_log_id', $log->id)->count())->toBe(1);
    Event::assertDispatchedTimes(ChatMessageFlagged::class, 1);

    // Re-dispatch with same body
    dispatchDetectionSync($log->id, $body);

    expect(ChatModerationFlag::where('chat_message_log_id', $log->id)->count())->toBe(1);
    Event::assertDispatchedTimes(ChatMessageFlagged::class, 1);
});

it('fires ChatMessageFlagged exactly once per newly-created flag row', function () {
    Event::fake([ChatMessageFlagged::class]);

    $log = ChatMessageLog::factory()->create();

    // Body with all three patterns: phone + email + link → 3 new flags expected.
    dispatchDetectionSync($log->id, 'Reach me 01012345678 or me@example.com or https://t.me/foo');

    $newCount = ChatModerationFlag::where('chat_message_log_id', $log->id)->count();
    expect($newCount)->toBe(3);

    Event::assertDispatchedTimes(ChatMessageFlagged::class, $newCount);

    // Most-severe rank: phone wins among phone/email/link.
    $log->refresh();
    expect($log->flag_reason)->toBe(ChatMessageFlagReason::PhonePattern->value);
});

it('returns silently when log id does not exist', function () {
    Event::fake([ChatMessageFlagged::class]);

    dispatchDetectionSync(999999999, 'Phone: 01012345678');

    expect(ChatModerationFlag::count())->toBe(0);
    Event::assertNotDispatched(ChatMessageFlagged::class);
});

it('returns silently when body preview is empty', function () {
    Event::fake([ChatMessageFlagged::class]);

    $log = ChatMessageLog::factory()->create();

    dispatchDetectionSync($log->id, '   ');

    expect(ChatModerationFlag::count())->toBe(0);
    Event::assertNotDispatched(ChatMessageFlagged::class);
});
