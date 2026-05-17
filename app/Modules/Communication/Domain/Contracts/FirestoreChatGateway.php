<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

interface FirestoreChatGateway
{
    public function freezeThread(string $firestoreThreadId): void;

    public function unfreezeThread(string $firestoreThreadId): void;

    /**
     * Write a message to Firestore and return the Firestore message document ID.
     */
    public function sendMessage(string $firestoreThreadId, string $senderUserId, string $body): string;
}
