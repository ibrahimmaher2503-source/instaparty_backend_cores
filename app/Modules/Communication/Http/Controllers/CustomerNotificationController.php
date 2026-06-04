<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Modules\Communication\Application\Actions\DeleteCustomerNotificationAction;
use App\Modules\Communication\Application\Actions\MarkAllNotificationsReadAction;
use App\Modules\Communication\Application\Actions\MarkNotificationReadAction;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Http\Resources\CustomerNotificationResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerNotificationController
{
    public function index(Request $request): JsonResponse
    {
        $page = $this->inbox((int) auth()->id())->orderByDesc('id')->cursorPaginate(20);

        return ApiResponse::success(
            CustomerNotificationResource::collection($page->items()),
            ['next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages()],
        );
    }

    public function unreadCount(): JsonResponse
    {
        return ApiResponse::success(['unread_count' => $this->inbox((int) auth()->id())->whereNull('read_at')->count()]);
    }

    public function markRead(string $publicId, MarkNotificationReadAction $action): JsonResponse
    {
        return ApiResponse::success(new CustomerNotificationResource($action->execute((int) auth()->id(), $publicId)));
    }

    public function markAllRead(MarkAllNotificationsReadAction $action): JsonResponse
    {
        $action->execute((int) auth()->id());

        return ApiResponse::success(null);
    }

    public function destroy(string $publicId, DeleteCustomerNotificationAction $action): JsonResponse
    {
        $action->execute((int) auth()->id(), $publicId);

        return ApiResponse::success(null, [], 200);
    }

    private function inbox(int $userId): Builder
    {
        return NotificationDispatch::query()
            ->where('user_id', $userId)
            ->where('channel', NotificationChannel::InApp);
    }
}
