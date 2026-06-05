<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Admin;

use App\Modules\Communication\Application\Actions\UnfreezeChatAction;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Http\Requests\Admin\UnfreezeChatRequest;
use App\Modules\Communication\Http\Resources\ChatThreadResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 */
class UnfreezeChatController
{
    public function __invoke(UnfreezeChatRequest $request, ChatThread $thread, UnfreezeChatAction $action): JsonResponse
    {
        $updated = $action->execute($thread, $request->toDTO(), $request->user());

        return ApiResponse::success((new ChatThreadResource($updated))->toArray($request));
    }
}
