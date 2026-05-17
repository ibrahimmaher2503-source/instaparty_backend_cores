<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestClarificationReplied;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequestMessage;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReplyToServiceChangeClarificationAction
{
    /**
     * @param  array{en: string, ar: string}  $body
     */
    public function execute(ServiceChangeRequest $cr, int $vendorUserId, array $body): ServiceChangeRequest
    {
        return DB::transaction(function () use ($cr, $vendorUserId, $body): ServiceChangeRequest {
            if ($cr->status !== ServiceChangeRequestStatus::AwaitingClarification) {
                throw new HttpResponseException(
                    response()->json([
                        'data' => null,
                        'meta' => [],
                        'errors' => [[
                            'code' => 'service_change_request.not_awaiting_clarification',
                            'title' => 'This change request is not awaiting clarification.',
                        ]],
                    ], Response::HTTP_CONFLICT)
                );
            }

            // Transition back to pending for admin review
            ServiceChangeRequest::query()
                ->where('id', $cr->id)
                ->update([
                    'status' => ServiceChangeRequestStatus::Pending->value,
                    'version' => $cr->version + 1,
                ]);

            // Append message row (append-only — do not update after creation)
            $message = ServiceChangeRequestMessage::query()->create([
                'service_change_request_id' => $cr->id,
                'author_user_id' => $vendorUserId,
                'author_role' => 'vendor',
                'body' => $body,
                'clarification_round' => $cr->clarification_round,
            ]);

            $cr->refresh();

            DB::afterCommit(fn () => event(new ServiceChangeRequestClarificationReplied($cr, $message)));

            return $cr;
        });
    }
}
