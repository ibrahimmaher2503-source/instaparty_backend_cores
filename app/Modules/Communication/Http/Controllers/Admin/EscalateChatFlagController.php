<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Admin;

use App\Modules\Communication\Application\Actions\EscalateChatFlagToAdminInboxAction;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Http\Requests\Admin\EscalateChatFlagRequest;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class EscalateChatFlagController extends Controller
{
    public function __invoke(
        EscalateChatFlagRequest $request,
        ChatModerationFlag $flag,
        EscalateChatFlagToAdminInboxAction $action,
    ): JsonResponse {
        $item = $action->execute($flag, $request->toDTO($flag->id), $request->user());

        return ApiResponse::success([
            'public_id'   => $item->public_id,
            'admin_id'    => $item->admin_id,
            'severity'    => $item->severity instanceof \BackedEnum ? $item->severity->value : (string) $item->severity,
            'status'      => $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status,
            'source_type' => $item->source_type,
            'source_id'   => $item->source_id,
        ]);
    }
}
