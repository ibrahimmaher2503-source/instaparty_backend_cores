<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Exceptions;

use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown by ResolveChatFlagAction when the target flag has already been resolved
 * (reviewed_at IS NOT NULL). Loud failure per R5 — re-resolution means workflow drift.
 *
 * Renders HTTP 409 Conflict with a bilingual ApiResponse envelope.
 */
class ChatFlagAlreadyResolved extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'chat_flag_already_resolved');
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            [
                [
                    'code' => 'chat_flag_already_resolved',
                    'message_en' => __('chat_moderation.errors.flag_already_resolved', [], 'en'),
                    'message_ar' => __('chat_moderation.errors.flag_already_resolved', [], 'ar'),
                ],
            ],
            409,
        );
    }
}
