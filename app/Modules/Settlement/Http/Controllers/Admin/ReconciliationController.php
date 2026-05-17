<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Controllers\Admin;

use App\Modules\Settlement\Application\Actions\RunReconciliationAction;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use App\Modules\Settlement\Http\Requests\TriggerReconciliationRequest;
use App\Modules\Settlement\Http\Resources\ReconciliationRunResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ReconciliationController extends Controller
{
    public function __construct(private RunReconciliationAction $runReconciliation) {}

    public function trigger(TriggerReconciliationRequest $request): JsonResponse
    {
        $result = $this->runReconciliation->execute($request->toInput());

        return ApiResponse::success(
            ['run_public_id' => $result->runPublicId, 'was_replay' => $result->wasIdempotentReplay],
            status: $result->wasIdempotentReplay ? 200 : 202,
        );
    }

    public function status(): JsonResponse
    {
        $run = ReconciliationRun::query()->latest('created_at')->first();

        return ApiResponse::success($run ? new ReconciliationRunResource($run) : null);
    }
}
