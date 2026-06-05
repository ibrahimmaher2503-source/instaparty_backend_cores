<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Modules\Communication\Application\Actions\UpdateNotificationPreferenceAction;
use App\Modules\Communication\Application\DTOs\NotificationPreferenceDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Http\Requests\UpdateNotificationPreferenceRequest;
use App\Modules\Communication\Http\Resources\NotificationPreferenceResource;
use App\Modules\Communication\Infrastructure\Repositories\EloquentNotificationPreferenceRepository;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Notification Preferences
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly EloquentNotificationPreferenceRepository $repository,
        private readonly UpdateNotificationPreferenceAction $updateAction,
    ) {}

    public function indexCustomer(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $prefs = $this->repository->listForUser($user->id);

        return ApiResponse::success(NotificationPreferenceResource::collection($prefs));
    }

    public function updateCustomer(
        UpdateNotificationPreferenceRequest $request,
        string $channel,
        string $eventCategory
    ): JsonResponse {
        $channelEnum = NotificationChannel::tryFrom($channel);
        $categoryEnum = EventCategory::tryFrom($eventCategory);

        if ($channelEnum === null || $categoryEnum === null) {
            return ApiResponse::error('Invalid channel or event_category.');
        }

        /** @var User $user */
        $user = $request->user();
        $pref = $this->updateAction->execute(new NotificationPreferenceDTO(
            userId: $user->id,
            channel: $channelEnum,
            eventCategory: $categoryEnum,
            isEnabled: $request->boolean('is_enabled'),
            quietHoursStart: $request->input('quiet_hours_start'),
            quietHoursEnd: $request->input('quiet_hours_end'),
            timezone: $request->input('timezone'),
        ));

        return ApiResponse::success(new NotificationPreferenceResource($pref));
    }

    public function indexVendor(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $prefs = $this->repository->listForUser($user->id);

        return ApiResponse::success(NotificationPreferenceResource::collection($prefs));
    }

    public function updateVendor(
        UpdateNotificationPreferenceRequest $request,
        string $channel,
        string $eventCategory
    ): JsonResponse {
        $channelEnum = NotificationChannel::tryFrom($channel);
        $categoryEnum = EventCategory::tryFrom($eventCategory);

        if ($channelEnum === null || $categoryEnum === null) {
            return ApiResponse::error('Invalid channel or event_category.');
        }

        /** @var User $user */
        $user = $request->user();
        $pref = $this->updateAction->execute(new NotificationPreferenceDTO(
            userId: $user->id,
            channel: $channelEnum,
            eventCategory: $categoryEnum,
            isEnabled: $request->boolean('is_enabled'),
            quietHoursStart: $request->input('quiet_hours_start'),
            quietHoursEnd: $request->input('quiet_hours_end'),
            timezone: $request->input('timezone'),
        ));

        return ApiResponse::success(new NotificationPreferenceResource($pref));
    }
}
