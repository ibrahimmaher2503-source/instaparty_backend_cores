<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Enums\ChatFlagType;

/**
 * Value object returned by {@see MessagePatternDetector::detect}.
 *
 * One MatchedPattern per (flag_type, normalized body) — duplicates of the same
 * flag_type within one body are collapsed to a single instance carrying the first
 * matched snippet. This honors the UNIQUE (chat_message_log_id, flag_type) index
 * on `chat_moderation_flags`.
 */
final readonly class MatchedPattern
{
    public function __construct(
        public ChatFlagType $flagType,
        public string $matchedPattern,
    ) {}
}
