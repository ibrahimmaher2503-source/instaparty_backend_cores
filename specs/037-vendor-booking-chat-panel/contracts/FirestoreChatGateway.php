<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

/**
 * Contract for all Firestore chat operations.
 *
 * Amended by spec 037: added sendMessage().
 * Stub implementation: FirestoreChatGatewayStub (returns fake IDs).
 * Production implementation: FirestoreChatGateway (Phase 2 — real SDK).
 */
interface FirestoreChatGateway
{
    public function freezeThread(string $firestoreThreadId): void;

    public function unfreezeThread(string $firestoreThreadId): void;

    /**
     * Send a text message to a Firestore thread.
     *
     * Called AFTER the DB::transaction commits (via DB::afterCommit).
     * Returns the Firestore-generated message document ID stored in
     * chat_message_log.firestore_message_id.
     */
    public function sendMessage(
        string $firestoreThreadId,
        string $senderUserId,
        string $body,
    ): string;
}
