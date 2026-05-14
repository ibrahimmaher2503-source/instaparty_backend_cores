<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Events\ChangeRequestCreated;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Shared\Domain\Policies\ChangeRequestPolicy;
use Illuminate\Support\Facades\DB;

class RequestDigitalServiceChangesAction
{
    public function execute(Service $service, array $items, User $admin, string $idempotencyKey): ChangeRequest
    {
        $policy = app(ChangeRequestPolicy::class);
        $routeName = request()->route()?->getName() ?? 'catalog.filament.service.request-changes';

        abort_if(
            ! $service->status->canTransitionTo(ServiceStatus::ChangesRequested),
            422,
        );

        abort_if(
            DB::table('idempotency_keys')
                ->where('user_id', $admin->id)
                ->where('route', $routeName)
                ->where('key', $idempotencyKey)
                ->exists(),
            409,
        );

        // Block if there's an open CR (vendor hasn't responded to current request)
        $openChangeRequest = $service->changeRequests()
            ->where('status', 'open')
            ->exists();
        abort_if($openChangeRequest, 409);

        // Check if we've hit the max cycle limit (3 cycles)
        $lastChangeRequest = $service->changeRequests()
            ->where('status', 'resubmitted')
            ->latest('cycle_number')
            ->first();
        abort_if(
            $lastChangeRequest && $lastChangeRequest->cycle_number >= 3,
            422,
            'Maximum change request cycles (3) reached. Must resolve or escalate.',
        );

        abort_if(
            ! $policy->canRequestChanges($service),
            422,
        );

        return DB::transaction(function () use ($service, $items, $admin, $idempotencyKey, $routeName): ChangeRequest {
            $nextCycleNumber = $service->changeRequests()->max('cycle_number') + 1 ?? 1;

            $changeRequest = ChangeRequest::create([
                'subject_type' => 'service',
                'subject_id' => $service->id,
                'requested_by_admin_id' => $admin->id,
                'status' => 'open',
                'cycle_number' => $nextCycleNumber,
            ]);

            foreach ($items as $item) {
                $changeRequest->items()->create([
                    'field_path' => $item['field_path'],
                    'current_value_snapshot' => null,
                    'requested_change_en' => $item['requested_change_en'],
                    'requested_change_ar' => $item['requested_change_ar'],
                    'item_status' => 'pending',
                ]);
            }

            $service->update(['status' => 'changes_requested']);

            DB::table('idempotency_keys')->insert([
                'key' => $idempotencyKey,
                'user_id' => $admin->id,
                'route' => $routeName,
                'request_hash' => hash('sha256', request()->getContent() ?: ''),
                'response_status' => 201,
                'response_body' => json_encode(['data' => $changeRequest]),
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
            ]);

            DB::afterCommit(fn () => event(new ChangeRequestCreated($changeRequest)));

            return $changeRequest;
        });
    }
}
