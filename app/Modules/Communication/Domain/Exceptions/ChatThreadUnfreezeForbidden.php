<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Exceptions;

use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown by UnfreezeChatAction when the underlying booking_vendor sub_status is
 * outside the legitimate review window (i.e., not 'pending' or 'modified').
 *
 * Renders HTTP 409 Conflict with a bilingual ApiResponse envelope.
 */
class ChatThreadUnfreezeForbidden extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'chat_thread_unfreeze_forbidden');
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            [
                [
                    'code' => 'chat_thread_unfreeze_forbidden',
                    'message_en' => __('chat_moderation.errors.unfreeze_forbidden_outside_window', [], 'en'),
                    'message_ar' => __('chat_moderation.errors.unfreeze_forbidden_outside_window', [], 'ar'),
                ],
            ],
            409,
        );
    }
}
