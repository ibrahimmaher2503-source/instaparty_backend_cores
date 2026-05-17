<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Models\User;

/**
 * The inviolable invariant test required by SC-004 / FR-EXT-036-015.
 *
 * `chat_message_log` is append-only after insert. Only `flagged`,
 * `flag_reason`, and `redacted` may be UPDATEd. The model's `booted()`
 * `updating` hook throws \LogicException on any other dirty column.
 */
function makeLogForInvariantTest(): ChatMessageLog
{
    $thread = ChatThread::factory()->create();

    return ChatMessageLog::factory()->create([
        'chat_thread_id' => $thread->id,
    ]);
}

it('throws LogicException when updating firestore_message_id', function () {
    $log = makeLogForInvariantTest();

    $log->firestore_message_id = 'tampered-id';
    $log->save();
})->throws(LogicException::class, 'append-only')->group('chat-moderation', 'invariant');

it('throws LogicException when updating sender_id', function () {
    $log = makeLogForInvariantTest();
    $otherUser = User::factory()->create();

    $log->sender_id = $otherUser->id;
    $log->save();
})->throws(LogicException::class, 'append-only')->group('chat-moderation', 'invariant');

it('throws LogicException when updating message_kind', function () {
    $log = makeLogForInvariantTest();

    $log->message_kind = 'image';
    $log->save();
})->throws(LogicException::class, 'append-only')->group('chat-moderation', 'invariant');

it('throws LogicException when updating detected_locale', function () {
    $log = makeLogForInvariantTest();

    // Pick a value guaranteed to differ from whatever the factory rolled.
    $log->detected_locale = $log->detected_locale === 'mixed' ? 'ar' : 'mixed';
    $log->save();
})->throws(LogicException::class, 'append-only')->group('chat-moderation', 'invariant');

it('allows updating flagged', function () {
    $log = makeLogForInvariantTest();

    $log->update(['flagged' => true]);

    expect($log->fresh()->flagged)->toBeTrue();
})->group('chat-moderation', 'invariant');

it('allows updating flag_reason', function () {
    $log = makeLogForInvariantTest();

    $log->update(['flag_reason' => 'manual']);

    expect($log->fresh()->flag_reason)->toBe('manual');
})->group('chat-moderation', 'invariant');

it('allows updating redacted', function () {
    $log = makeLogForInvariantTest();

    $log->update(['redacted' => true]);

    expect($log->fresh()->redacted)->toBeTrue();
})->group('chat-moderation', 'invariant');

it('throws when updating a mix of allowed and forbidden columns', function () {
    $log = makeLogForInvariantTest();

    $log->flagged = true; // allowed
    $log->firestore_message_id = 'tampered'; // forbidden
    $log->save();
})->throws(LogicException::class, 'firestore_message_id')->group('chat-moderation', 'invariant');
