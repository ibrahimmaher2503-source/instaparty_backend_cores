<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Events\ChangeRequestResubmitted;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use Illuminate\Support\Facades\DB;

class ServiceResubmitAfterChangesAction
{
    public function execute(Service $service, array $changedFields, User $vendor, string $idempotencyKey): ChangeRequest
    {
        abort_if(
            DB::table('idempotency_keys')
                ->where('user_id', $vendor->id)
                ->where('route', request()->route()->getName())
                ->where('key', $idempotencyKey)
                ->exists(),
            409,
        );

        $changeRequest = $service->changeRequests()
            ->where('status', 'open')
            ->latest()
            ->first();
        abort_if(! $changeRequest, 422);

        return DB::transaction(function () use ($service, $changeRequest, $changedFields, $vendor, $idempotencyKey): ChangeRequest {
            foreach ($changedFields as $fieldPath => $newValue) {
                $item = $changeRequest->items()
                    ->where('field_path', $fieldPath)
                    ->first();

                if ($item) {
                    $item->update([
                        'item_status' => 'addressed',
                    ]);
                }
            }

            $service->update($changedFields + ['status' => 'pending_review']);

            $changeRequest->update(['status' => 'resubmitted']);

            DB::table('idempotency_keys')->insert([
                'user_id' => $vendor->id,
                'route' => request()->route()->getName(),
                'key' => $idempotencyKey,
                'result_type' => 'change_request',
                'result_id' => $changeRequest->id,
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
            ]);

            DB::afterCommit(fn () => event(new ChangeRequestResubmitted($changeRequest, $service)));

            return $changeRequest;
        });
    }
}
