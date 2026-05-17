<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\DecideServiceChangeRequestDTO;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestApproved;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApproveServiceChangeRequestAction
{
    public function __construct(
        private readonly ApplyServiceChangeToLiveAction $applyAction,
    ) {}

    public function execute(ServiceChangeRequest $cr, DecideServiceChangeRequestDTO $dto): ServiceChangeRequest
    {
        return DB::transaction(function () use ($cr, $dto): ServiceChangeRequest {
            // Optimistic-lock version-check UPDATE — atomically asserts the caller
            // holds the latest version and the request is still in an open state.
            $affected = ServiceChangeRequest::query()
                ->where('id', $cr->id)
                ->where('version', $dto->version)
                ->whereIn('status', [
                    ServiceChangeRequestStatus::Pending->value,
                    ServiceChangeRequestStatus::AwaitingClarification->value,
                ])
                ->update([
                    'status' => ServiceChangeRequestStatus::Approved->value,
                    'decided_by' => $dto->adminUserId,
                    'decided_at' => now(),
                    'admin_note' => $dto->adminNote !== null
                        ? json_encode($dto->adminNote, JSON_UNESCAPED_UNICODE)
                        : null,
                    'version' => $dto->version + 1,
                ]);

            if ($affected === 0) {
                throw new HttpResponseException(
                    response()->json([
                        'data' => null,
                        'meta' => [],
                        'errors' => [[
                            'code' => 'service_change_request.version_mismatch',
                            'title' => __('catalog::service_change_request.version_mismatch'),
                        ]],
                    ], Response::HTTP_CONFLICT)
                );
            }

            $cr->refresh();

            // Apply proposed changes to the live service + detail rows inside
            // the same transaction so the approval and the field writes are atomic.
            $appliedFieldPaths = $this->applyAction->execute($cr);

            DB::afterCommit(fn () => event(
                new ServiceChangeRequestApproved($cr, $appliedFieldPaths)
            ));

            return $cr;
        });
    }
}
