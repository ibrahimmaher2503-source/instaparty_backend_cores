<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Application\Services\TemplateNotFoundException;
use App\Modules\Communication\Application\Services\TemplateResolver;
use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Events\NotificationDispatched;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DispatchNotificationAction implements NotificationDispatcher
{
    public function __construct(
        private readonly TemplateResolver $templateResolver,
    ) {}

    public function execute(DispatchNotificationDTO $dto): void
    {
        if (! NotificationPreference::isEnabledFor($dto->userId, $dto->channel, $dto->eventCategory)) {
            return;
        }

        $locale = $this->resolveLocale($dto->userId);

        try {
            $resolved = $this->templateResolver->resolve(
                $dto->eventKey,
                $dto->channel,
                $dto->audience,
                $locale,
            );
        } catch (TemplateNotFoundException $e) {
            $this->writeFailedDispatch($dto, $locale, $e->getMessage());

            return;
        }

        $context = array_merge($dto->context, [
            'body' => $resolved->body,
            'subject' => $resolved->subject,
        ]);

        $dispatch = DB::transaction(function () use ($dto, $resolved, $context, $locale): NotificationDispatch {
            $dispatch = NotificationDispatch::create([
                'public_id' => Str::ulid()->toBase32(),
                'notification_template_id' => $resolved->templateId,
                'user_id' => $dto->userId,
                'channel' => $dto->channel->value,
                'locale' => $locale,
                'status' => DispatchStatus::Queued->value,
                'context' => $context,
                'reference_type' => $dto->referenceType,
                'reference_id' => $dto->referenceId,
            ]);

            DB::afterCommit(function () use ($dispatch) {
                try {
                    $adapter = $this->resolveAdapter($dispatch->channel);
                    $adapter->send($dispatch);
                } catch (\Throwable $e) {
                    Log::error('DispatchNotificationAction: adapter error', [
                        'dispatch_id' => $dispatch->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

            DB::afterCommit(function () use ($dispatch) {
                NotificationDispatched::dispatch($dispatch);
            });

            return $dispatch;
        });
    }

    public function dispatch(
        string $eventKey,
        int $userId,
        NotificationAudience $audience,
        array $context = [],
        ?string $referenceType = null,
        ?int $referenceId = null
    ): void {
        // Determine event category from event key prefix
        $category = $this->categoryFromEventKey($eventKey);

        $channels = $this->channelsForAudience($audience);

        foreach ($channels as $channel) {
            $this->execute(new DispatchNotificationDTO(
                eventKey: $eventKey,
                channel: $channel,
                audience: $audience,
                eventCategory: $category,
                userId: $userId,
                context: $context,
                referenceType: $referenceType,
                referenceId: $referenceId,
            ));
        }
    }

    private function resolveLocale(int $userId): string
    {
        try {
            /** @var \App\Modules\Identity\Domain\Models\User|null $user */
            $user = User::find($userId);

            return $user !== null ? ($user->preferred_locale ?? app()->getLocale()) : app()->getLocale();
        } catch (\Throwable) {
            return 'en';
        }
    }

    private function resolveAdapter(NotificationChannel $channel): NotificationChannelAdapter
    {
        return app($channel->value.'_adapter');
    }

    private function writeFailedDispatch(DispatchNotificationDTO $dto, string $locale, string $error): void
    {
        try {
            NotificationDispatch::create([
                'public_id' => Str::ulid()->toBase32(),
                'notification_template_id' => null,
                'user_id' => $dto->userId,
                'channel' => $dto->channel->value,
                'locale' => $locale,
                'status' => DispatchStatus::Failed->value,
                'context' => $dto->context,
                'reference_type' => $dto->referenceType,
                'reference_id' => $dto->referenceId,
                'error_message' => $error,
            ]);
        } catch (\Throwable $e) {
            Log::error('DispatchNotificationAction: could not write failed dispatch row', ['error' => $e->getMessage()]);
        }
    }

    private function categoryFromEventKey(string $eventKey): EventCategory
    {
        $parts  = explode('.', $eventKey);
        $prefix = $parts[0];

        return match ($prefix) {
            'booking' => EventCategory::Booking,
            'payment' => EventCategory::Payment,
            'review' => EventCategory::Review,
            'chat' => EventCategory::Chat,
            'marketing' => EventCategory::Marketing,
            'rental', 'sale', 'digital' => EventCategory::Booking,
            default => EventCategory::System,
        };
    }

    private function channelsForAudience(NotificationAudience $audience): array
    {
        return [NotificationChannel::Push, NotificationChannel::Email];
    }
}
