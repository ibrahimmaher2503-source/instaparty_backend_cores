<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\SendVendorChatMessageDTO;
use App\Modules\Communication\Application\Services\ChatPanelStateResolver;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatPanelState;
use App\Modules\Communication\Domain\Events\ChatFlagged;
use App\Modules\Communication\Domain\Events\ChatMessageFlagged;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Infrastructure\Services\MessagePatternDetector;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class SendVendorChatMessageAction
{
    public function __construct(
        private readonly ChatPanelStateResolver $stateResolver,
        private readonly MessagePatternDetector $detector,
        private readonly FirestoreChatGateway $gateway,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @throws \DomainException When ownership fails, state is not Open, or duplicate submission.
     */
    public function execute(
        ChatThread $thread,
        VendorProfile $vendorProfile,
        SendVendorChatMessageDTO $dto,
    ): ChatMessageLog {
        $this->assertOwnership($thread, $vendorProfile);
        $this->assertThreadOpen($thread, $vendorProfile);
        $this->assertNotDuplicate($thread, $vendorProfile, $dto->body);

        $patterns = $this->detector->detect($dto->body);
        $isBlocked = $patterns !== [];

        return DB::transaction(function () use ($thread, $vendorProfile, $dto, $patterns, $isBlocked): ChatMessageLog {
            $log = $this->createMessageLog($thread, $vendorProfile, $dto->body, $isBlocked);

            if ($isBlocked) {
                $flags = $this->createModerationFlags($log, $patterns);
                $this->writeAuditRow($thread, $vendorProfile, $log, 'chat.message.blocked');

                DB::afterCommit(static function () use ($thread, $log, $flags): void {
                    event(new ChatFlagged($thread->id));
                    foreach ($flags as $flag) {
                        event(new ChatMessageFlagged($log, $flag));
                    }
                });
            } else {
                $this->writeAuditRow($thread, $vendorProfile, $log, 'chat.message.sent');

                $gateway = $this->gateway;
                DB::afterCommit(static function () use ($thread, $vendorProfile, $dto, $gateway): void {
                    try {
                        $gateway->sendMessage(
                            $thread->firestore_thread_id ?? '',
                            (string) $vendorProfile->user_id,
                            $dto->body,
                        );
                    } catch (\Throwable $e) {
                        // Gateway failure is non-fatal: the audit row is committed.
                        Log::warning('FirestoreChatGateway::sendMessage failed', [
                            'thread_id' => $thread->id,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                });
            }

            return $log;
        });
    }

    private function assertOwnership(ChatThread $thread, VendorProfile $vendorProfile): void
    {
        if ($thread->vendor_profile_id !== $vendorProfile->id) {
            throw new \DomainException('Vendor does not own this chat thread.');
        }
    }

    private function assertThreadOpen(ChatThread $thread, VendorProfile $vendorProfile): void
    {
        $booking = $thread->booking;
        $state   = $this->stateResolver->resolve($thread, $booking);

        if ($state !== ChatPanelState::Open) {
            throw new \DomainException('Chat thread is not open for sending.');
        }
    }

    private function assertNotDuplicate(ChatThread $thread, VendorProfile $vendorProfile, string $body): void
    {
        $key = sprintf('chat_dedup:%d:%d:%s', $thread->id, $vendorProfile->id, md5($body));

        if ($this->cache->has($key)) {
            throw new \DomainException('Duplicate message submission detected.');
        }

        $this->cache->put($key, true, 10); // 10-second TTL per ADR ID-037-2
    }

    private function createMessageLog(
        ChatThread $thread,
        VendorProfile $vendorProfile,
        string $body,
        bool $isBlocked,
    ): ChatMessageLog {
        $log = new ChatMessageLog();
        $log->public_id           = Str::ulid()->toBase32();
        $log->chat_thread_id      = $thread->id;
        $log->sender_id           = $vendorProfile->user_id;
        $log->firestore_message_id = null;
        $log->message_kind        = 'vendor_text';
        $log->detected_locale     = $this->detectLocale($body);
        $log->flagged             = $isBlocked;
        $log->flag_reason         = null;
        $log->redacted            = false;
        $log->save();

        return $log;
    }

    /**
     * @param  \App\Modules\Communication\Infrastructure\Services\MatchedPattern[] $patterns
     * @return ChatModerationFlag[]
     */
    private function createModerationFlags(ChatMessageLog $log, array $patterns): array
    {
        $flags = [];

        foreach ($patterns as $pattern) {
            $flags[] = ChatModerationFlag::create([
                'chat_message_log_id' => $log->id,
                'flag_type'           => $pattern->flagType,
                'matched_pattern'     => $pattern->matchedPattern,
                'action_taken'        => ChatFlagAction::Block,
            ]);
        }

        return $flags;
    }

    private function writeAuditRow(
        ChatThread $thread,
        VendorProfile $vendorProfile,
        ChatMessageLog $log,
        string $action,
    ): void {
        DB::table('audit_logs')->insert([
            'public_id'      => Str::ulid()->toBase32(),
            'auditable_type' => ChatThread::class,
            'auditable_id'   => $thread->id,
            'user_id'        => $vendorProfile->user_id,
            'action'         => $action,
            'changes'        => json_encode(['chat_message_log_id' => $log->id], JSON_UNESCAPED_UNICODE),
            'created_at'     => now(),
        ]);
    }

    private function detectLocale(string $body): string
    {
        return preg_match('/\p{Arabic}/u', $body) === 1 ? 'ar' : 'en';
    }
}
