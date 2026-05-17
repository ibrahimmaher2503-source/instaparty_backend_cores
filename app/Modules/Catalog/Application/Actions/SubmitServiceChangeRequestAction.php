<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\ServiceFieldDiff;
use App\Modules\Catalog\Application\DTOs\SubmitServiceChangeRequestDTO;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestSubmitted;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequestItem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SubmitServiceChangeRequestAction
{
    public function execute(SubmitServiceChangeRequestDTO $dto, ServiceFieldDiff $diff): ServiceChangeRequest
    {
        return DB::transaction(function () use ($dto, $diff): ServiceChangeRequest {
            // Lock the service row to prevent concurrent submissions
            $service = Service::query()
                ->where('id', $dto->serviceId)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check for an open request inside the lock (defence-in-depth vs the DB generated-column UNIQUE)
            $hasOpen = ServiceChangeRequest::query()
                ->where('service_id', $dto->serviceId)
                ->where(fn ($q) => $q
                    ->where('status', ServiceChangeRequestStatus::Pending->value)
                    ->orWhere('status', ServiceChangeRequestStatus::AwaitingClarification->value)
                )
                ->lockForUpdate()
                ->exists();

            if ($hasOpen) {
                throw new HttpResponseException(
                    response()->json([
                        'data' => null,
                        'meta' => [],
                        'errors' => [[
                            'code' => 'service_edit.pending_request_exists',
                            'title' => __('catalog.service_change_request.pending_request_exists'),
                            'title_ar' => __('ar.catalog.service_change_request.pending_request_exists'),
                            'open_request_public_id' => ServiceChangeRequest::query()
                                ->where('service_id', $dto->serviceId)
                                ->where(fn ($q) => $q
                                    ->where('status', ServiceChangeRequestStatus::Pending->value)
                                    ->orWhere('status', ServiceChangeRequestStatus::AwaitingClarification->value)
                                )
                                ->value('public_id'),
                            'open_request_status' => ServiceChangeRequestStatus::Pending->value,
                        ]],
                    ], Response::HTTP_CONFLICT)
                );
            }

            // Build the before_snapshot from the current live service state
            $beforeSnapshot = [
                'name' => $service->getRawOriginal('name'),
                'short_description' => $service->getRawOriginal('short_description'),
                'long_description' => $service->getRawOriginal('long_description'),
                'base_price_minor' => $service->base_price_minor,
                'base_price_currency' => $service->base_price_currency ?? 'EGP',
                'category_id' => $service->category_id,
                'product_type' => $service->product_type->value,
            ];

            // Structured proposed_changes blob (R7 from research.md)
            $proposedChanges = [
                'shared' => [],
                'type_specific' => [],
                'gallery_ops' => $dto->proposedPayload['gallery_ops'] ?? [],
                'availability_windows' => $dto->proposedPayload['availability_windows'] ?? [],
                'excluded_dates' => $dto->proposedPayload['excluded_dates'] ?? [],
                'pricing_tiers' => $dto->proposedPayload['pricing_tiers'] ?? [],
            ];

            foreach ($diff->materialItems() as $item) {
                $classification = $item['field_classification'];
                if (in_array($classification, ['shared'], true)) {
                    $proposedChanges['shared'][$item['field_path']] = $item['after_value'];
                } elseif (in_array($classification, ['rental', 'sale', 'digital'], true)) {
                    $proposedChanges['type_specific'][$item['field_path']] = $item['after_value'];
                }
            }

            /** @var ServiceChangeRequest $cr */
            $cr = ServiceChangeRequest::query()->create([
                'public_id' => (string) Str::ulid(),
                'service_id' => $service->id,
                'product_type' => $service->product_type->value,
                'vendor_profile_id' => $dto->vendorProfileId,
                'submitted_by' => $dto->vendorUserId,
                'status' => ServiceChangeRequestStatus::Pending->value,
                'proposed_changes' => $proposedChanges,
                'before_snapshot' => $beforeSnapshot,
                'vendor_note' => $dto->vendorNote,
                'clarification_round' => 0,
                'version' => 1,
            ]);

            // Insert per-field item rows (append-only)
            foreach ($diff->materialItems() as $item) {
                ServiceChangeRequestItem::query()->create([
                    'service_change_request_id' => $cr->id,
                    'field_path' => $item['field_path'],
                    'field_classification' => $item['field_classification'],
                    'before_value' => $item['before_value'],
                    'after_value' => $item['after_value'],
                ]);
            }

            $cr->load(['service', 'items']);

            DB::afterCommit(fn () => event(new ServiceChangeRequestSubmitted($cr)));

            return $cr;
        });
    }
}
