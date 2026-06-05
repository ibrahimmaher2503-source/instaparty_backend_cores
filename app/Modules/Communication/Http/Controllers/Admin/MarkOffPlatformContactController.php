<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Admin;

use App\Modules\Communication\Application\Actions\MarkOffPlatformContactAttemptAction;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Http\Requests\Admin\MarkOffPlatformContactRequest;
use App\Modules\Communication\Http\Resources\ChatModerationFlagResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * @group Admin
 */
class MarkOffPlatformContactController extends Controller
{
    public function __invoke(
        MarkOffPlatformContactRequest $request,
        ChatMessageLog $log,
        MarkOffPlatformContactAttemptAction $action,
    ): JsonResponse {
        $flag = $action->execute($log, $request->toDTO($log->id), $request->user());

        return ChatModerationFlagResource::make($flag)->response();
    }
}
