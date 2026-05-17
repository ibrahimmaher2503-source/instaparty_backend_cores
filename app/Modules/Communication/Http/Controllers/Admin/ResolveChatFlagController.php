<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Admin;

use App\Modules\Communication\Application\Actions\ResolveChatFlagAction;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Http\Requests\Admin\ResolveChatFlagRequest;
use App\Modules\Communication\Http\Resources\ChatModerationFlagResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ResolveChatFlagController extends Controller
{
    public function __invoke(
        ResolveChatFlagRequest $request,
        ChatModerationFlag $flag,
        ResolveChatFlagAction $action,
    ): JsonResponse {
        $resolved = $action->execute($flag, $request->toDTO(), $request->user());

        return ChatModerationFlagResource::make($resolved)->response();
    }
}
