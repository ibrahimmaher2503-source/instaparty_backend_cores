<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Admin;

use App\Modules\Communication\Application\Actions\FreezeChatAction;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Http\Requests\Admin\FreezeChatRequest;
use App\Modules\Communication\Http\Resources\ChatThreadResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class FreezeChatController
{
    public function __invoke(FreezeChatRequest $request, ChatThread $thread, FreezeChatAction $action): JsonResponse
    {
        $updated = $action->execute($thread, $request->toDTO(), $request->user());

        return ApiResponse::success((new ChatThreadResource($updated))->toArray($request));
    }
}
