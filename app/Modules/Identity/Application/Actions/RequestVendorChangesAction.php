<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Models\User;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Domain\Events\ChangeRequestCreated;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Shared\Domain\Models\ChangeRequestItem;
use App\Modules\Shared\Domain\Policies\ChangeRequestPolicy;
use Illuminate\Support\Facades\DB;

class RequestVendorChangesAction
{
    public function execute(VendorProfile $profile, array $items, User $admin, string $idempotencyKey): ChangeRequest
    {
        $this->checkIdempotency($idempotencyKey);
        $this->assertNoOpenRequest($profile);
        $this->assertCycleLimitNotExceeded($profile);

        return DB::transaction(function () use ($profile, $items, $admin, $idempotencyKey) {
            $nextCycleNumber = $this->getNextCycleNumber($profile);

            $changeRequest = ChangeRequest::create([
                'public_id' => fake()->ulid(),
                'subject_type' => 'vendor_profile',
                'subject_id' => $profile->id,
                'requested_by_admin_id' => $admin->id,
                'status' => ChangeRequestStatus::Open,
                'cycle_number' => $nextCycleNumber,
            ]);

            foreach ($items as $item) {
                ChangeRequestItem::create([
                    'public_id' => fake()->ulid(),
                    'change_request_id' => $changeRequest->id,
                    'field_path' => $item['field_path'],
                    'current_value_snapshot' => null,
                    'requested_change_en' => $item['requested_change_en'],
                    'requested_change_ar' => $item['requested_change_ar'],
                    'item_status' => 'pending',
                ]);
            }

            $profile->update(['approval_status' => ApprovalStatus::ChangesRequested->value]);

            DB::afterCommit(fn () => event(new ChangeRequestCreated($changeRequest)));

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

    private function assertNoOpenRequest(VendorProfile $profile): void
    {
        $existingRequest = ChangeRequest::where('subject_type', 'vendor_profile')
            ->where('subject_id', $profile->id)
            ->whereIn('status', [ChangeRequestStatus::Open->value, ChangeRequestStatus::Resubmitted->value])
            ->first();

        if ($existingRequest) {
            abort(409, 'Vendor profile already has an open change request');
        }
    }

    private function assertCycleLimitNotExceeded(VendorProfile $profile): void
    {
        $resolvedCount = ChangeRequest::where('subject_type', 'vendor_profile')
            ->where('subject_id', $profile->id)
            ->where('status', ChangeRequestStatus::Resolved->value)
            ->count();

        if ($resolvedCount >= ChangeRequestPolicy::MAX_CYCLES) {
            abort(422, 'Maximum change request cycles exceeded');
        }
    }

    private function getNextCycleNumber(VendorProfile $profile): int
    {
        return ChangeRequest::where('subject_type', 'vendor_profile')
            ->where('subject_id', $profile->id)
            ->max('cycle_number') + 1;
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
