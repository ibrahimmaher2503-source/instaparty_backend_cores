<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\DecideServiceChangeRequestDTO;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestClarificationRequested;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequestMessage;
use App\Modules\Catalog\Domain\Policies\ServiceEditApprovalPolicy;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequestServiceChangeClarificationAction
{
    public function execute(ServiceChangeRequest $cr, DecideServiceChangeRequestDTO $dto): ServiceChangeRequest
    {
        return DB::transaction(function () use ($cr, $dto): ServiceChangeRequest {
            // Cap check: enforce maximum clarification rounds
            if ($cr->clarification_round >= ServiceEditApprovalPolicy::MAX_CLARIFICATIONS) {
                throw new HttpResponseException(
                    response()->json([
                        'data' => null,
                        'meta' => [],
                        'errors' => [[
                            'code' => 'service_change_request.clarification_cap_reached',
                            'title' => __('catalog::service_change_request.clarification_cap_reached', [
                                'max' => ServiceEditApprovalPolicy::MAX_CLARIFICATIONS,
                            ]),
                        ]],
                    ], Response::HTTP_CONFLICT)
                );
            }

            $newRound = $cr->clarification_round + 1;

            // Optimistic-lock version-check: transition only if status is still open
            $affected = ServiceChangeRequest::query()
                ->where('id', $cr->id)
                ->where('version', $dto->version)
                ->whereIn('status', [
                    ServiceChangeRequestStatus::Pending->value,
                    ServiceChangeRequestStatus::AwaitingClarification->value,
                ])
                ->update([
                    'status' => ServiceChangeRequestStatus::AwaitingClarification->value,
                    'clarification_round' => $newRound,
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

            // Append message row (append-only — do not update after creation)
            $message = ServiceChangeRequestMessage::query()->create([
                'service_change_request_id' => $cr->id,
                'author_user_id' => $dto->adminUserId,
                'author_role' => 'admin',
                'body' => $dto->adminNote,
                'clarification_round' => $newRound,
            ]);

            DB::afterCommit(fn () => event(new ServiceChangeRequestClarificationRequested($cr, $message)));

            return $cr;
        });
    }
}
