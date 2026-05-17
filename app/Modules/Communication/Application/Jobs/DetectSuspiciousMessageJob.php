<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Jobs;

use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Enums\ChatMessageFlagReason;
use App\Modules\Communication\Domain\Events\ChatMessageFlagged;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Infrastructure\Services\MatchedPattern;
use App\Modules\Communication\Infrastructure\Services\MessagePatternDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Detects suspicious patterns in a chat message body and creates moderation flags.
 *
 * Source-of-truth contract (research.md §R9):
 *  - `chat_message_log` MIRRORS Firestore messages but does NOT store the body.
 *  - The Firestore listener that performs the INSERT MUST pass the body via
 *    {@see dispatchWithBody()} so this job has content to analyse.
 *  - Without a body the job is a no-op (returns silently).
 *
 * Idempotency:
 *  - `ChatModerationFlag` has UNIQUE (chat_message_log_id, flag_type). Concurrent
 *    or retried jobs reconverge via `firstOrCreate`.
 *  - `ChatMessageFlagged` fires ONLY for newly-created flag rows (gated on
 *    `wasRecentlyCreated`).
 *
 * Severity rank (most-severe wins on `chat_message_log.flag_reason`):
 *    phone (5) > email (4) > external_link (3) > profanity (2) > manual (1) > none (0)
 */
class DetectSuspiciousMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $chatMessageLogId,
        public readonly ?string $bodyPreview = null,
    ) {
        $this->onQueue('chat-moderation');
    }

    /**
     * Public dispatch path used by the upstream Firestore listener.
     *
     * Since `chat_message_log` does not persist the body (Firestore is the
     * source of truth), the upstream listener MUST hand the body to the job
     * via this helper. The listener itself is out of scope for this story.
     */
    public static function dispatchWithBody(int $logId, string $body): void
    {
        self::dispatch($logId, $body);
    }

    public function handle(MessagePatternDetector $detector): void
    {
        $log = ChatMessageLog::find($this->chatMessageLogId);

        if ($log === null) {
            return;
        }

        $body = $this->bodyPreview;

        if ($body === null || trim($body) === '') {
            return;
        }

        $matches = $detector->detect($body);

        if ($matches === []) {
            return;
        }

        $newFlags = [];

        DB::transaction(function () use ($log, $matches, &$newFlags): void {
            foreach ($matches as $match) {
                /** @var MatchedPattern $match */
                $flag = ChatModerationFlag::firstOrCreate(
                    [
                        'chat_message_log_id' => $log->id,
                        'flag_type' => $match->flagType,
                    ],
                    [
                        'matched_pattern' => $match->matchedPattern,
                        'action_taken' => ChatFlagAction::Warn,
                    ],
                );

                if ($flag->wasRecentlyCreated) {
                    $newFlags[] = $flag;
                }
            }

            if ($newFlags !== []) {
                $mostSevere = $this->mostSevereReason(array_map(
                    static fn (ChatModerationFlag $f): ChatFlagType => $f->flag_type,
                    $newFlags,
                ));

                $log->update([
                    'flagged' => true,
                    'flag_reason' => $mostSevere->value,
                ]);
            }
        });

        foreach ($newFlags as $flag) {
            DB::afterCommit(fn () => event(new ChatMessageFlagged($log, $flag)));
        }
    }

    /**
     * Resolve the most-severe ChatMessageFlagReason for a set of flag types.
     *
     * Rank: phone (5) > email (4) > external_link (3) > profanity (2) > manual (1).
     *
     * @param  array<int, ChatFlagType>  $flagTypes
     */
    private function mostSevereReason(array $flagTypes): ChatMessageFlagReason
    {
        $rank = static fn (ChatMessageFlagReason $r): int => match ($r) {
            ChatMessageFlagReason::PhonePattern => 5,
            ChatMessageFlagReason::EmailPattern => 4,
            ChatMessageFlagReason::ExternalLink => 3,
            ChatMessageFlagReason::Manual => 1,
            ChatMessageFlagReason::PostLock => 0,
        };

        $reasons = array_map(
            static fn (ChatFlagType $t): ChatMessageFlagReason => match ($t) {
                ChatFlagType::Phone => ChatMessageFlagReason::PhonePattern,
                ChatFlagType::Email => ChatMessageFlagReason::EmailPattern,
                ChatFlagType::ExternalLink => ChatMessageFlagReason::ExternalLink,
                ChatFlagType::Profanity, ChatFlagType::Other => ChatMessageFlagReason::Manual,
            },
            $flagTypes,
        );

        $winner = $reasons[0];
        foreach ($reasons as $reason) {
            if ($rank($reason) > $rank($winner)) {
                $winner = $reason;
            }
        }

        return $winner;
    }
}
