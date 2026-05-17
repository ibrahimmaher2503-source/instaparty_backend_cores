<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\DecideServiceChangeRequestDTO;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestRejected;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RejectServiceChangeRequestAction
{
    public function execute(ServiceChangeRequest $cr, DecideServiceChangeRequestDTO $dto): ServiceChangeRequest
    {
        return DB::transaction(function () use ($cr, $dto): ServiceChangeRequest {
            // Optimistic-lock version-check: reject only if status is still open
            $affected = ServiceChangeRequest::query()
                ->where('id', $cr->id)
                ->where('version', $dto->version)
                ->whereIn('status', [
                    ServiceChangeRequestStatus::Pending->value,
                    ServiceChangeRequestStatus::AwaitingClarification->value,
                ])
                ->update([
                    'status' => ServiceChangeRequestStatus::Rejected->value,
                    'decided_by' => $dto->adminUserId,
                    'decided_at' => now(),
                    'admin_note' => $dto->adminNote,
                    'version' => $dto->version + 1,
                ]);

            if ($affected === 0) {
                throw new HttpResponseException(
                    response()->json([
                        'data' => null,
                        'meta' => [],
                        'errors' => [[
                            'code' => 'service_change_request.version_mismatch',
                            'title' => 'This change request was already decided by another admin.',
                        ]],
                    ], Response::HTTP_CONFLICT)
                );
            }

            $cr->refresh();

            DB::afterCommit(fn () => event(new ServiceChangeRequestRejected($cr)));

            return $cr;
        });
    }
}
