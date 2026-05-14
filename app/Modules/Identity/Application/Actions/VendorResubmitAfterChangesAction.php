<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Events\VendorProfileResubmitted;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Shared\Domain\Models\ChangeRequestItem;
use Illuminate\Support\Facades\DB;

class VendorResubmitAfterChangesAction
{
    public function execute(
        VendorProfile $profile,
        array $addressedItemIds,
        array $waivedItemIds,
        ?string $notes,
        string $idempotencyKey,
    ): ChangeRequest {
        $this->checkIdempotency($idempotencyKey);
        $this->assertProfileHasChangesRequested($profile);

        $changeRequest = $this->getActiveChangeRequest($profile);
        $this->assertChangeRequestIsOpen($changeRequest);

        return DB::transaction(function () use ($profile, $changeRequest, $addressedItemIds, $waivedItemIds, $idempotencyKey) {
            foreach ($addressedItemIds as $itemId) {
                ChangeRequestItem::where('public_id', $itemId)->update(['item_status' => 'addressed']);
            }

            foreach ($waivedItemIds as $itemId) {
                ChangeRequestItem::where('public_id', $itemId)->update(['item_status' => 'waived']);
            }

            $changeRequest->update(['status' => ChangeRequestStatus::Resubmitted]);
            $profile->update(['approval_status' => ApprovalStatus::Pending->value]);

            DB::afterCommit(fn () => event(new VendorProfileResubmitted($profile, $changeRequest)));

            $this->storeIdempotencyResult($idempotencyKey, $changeRequest);

            return $changeRequest;
        });
    }

    private function checkIdempotency(string $idempotencyKey): void
    {
        $existing = DB::table('idempotency_keys')
            ->where('key', $idempotencyKey)
            ->where('user_id', auth()->id())
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            abort(409, 'Duplicate request');
        }
    }

    private function assertProfileHasChangesRequested(VendorProfile $profile): void
    {
        if ($profile->approval_status !== ApprovalStatus::ChangesRequested) {
            abort(422, 'Vendor profile does not have changes requested');
        }
    }

    private function getActiveChangeRequest(VendorProfile $profile): ChangeRequest
    {
        return ChangeRequest::where('subject_type', 'vendor_profile')
            ->where('subject_id', $profile->id)
            ->whereIn('status', [ChangeRequestStatus::Open->value, ChangeRequestStatus::Resubmitted->value])
            ->orderByDesc('cycle_number')
            ->firstOrFail();
    }

    private function assertChangeRequestIsOpen(ChangeRequest $changeRequest): void
    {
        if ($changeRequest->status !== ChangeRequestStatus::Open) {
            abort(422, 'Change request is not open');
        }
    }

    private function storeIdempotencyResult(string $idempotencyKey, ChangeRequest $changeRequest): void
    {
        DB::table('idempotency_keys')->insert([
            'key' => $idempotencyKey,
            'user_id' => auth()->id(),
            'route' => request()->route()?->getName(),
            'response_body' => json_encode(['change_request_id' => $changeRequest->id]),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ]);
    }
}
