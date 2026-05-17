<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApplyServiceChangeToLiveAction
{
    /**
     * Applies the proposed changes to the live service rows.
     * Must be called INSIDE an existing DB::transaction — does NOT open its own.
     *
     * @return list<string> List of applied field paths
     */
    public function execute(ServiceChangeRequest $cr): array
    {
        /** @var Service $service */
        $service = $cr->service()->lockForUpdate()->firstOrFail();
        $proposed = $cr->proposed_changes;
        $appliedPaths = [];

        // ── Shared base-table columns ─────────────────────────────────────────
        $sharedUpdates = [];
        foreach ($proposed['shared'] ?? [] as $path => $value) {
            // Translatable fields like name.en → stored as JSON {en, ar}
            if (str_contains((string) $path, '.')) {
                [$column, $locale] = explode('.', (string) $path, 2);
                $raw = $service->getRawOriginal($column);
                $current = is_string($raw)
                    ? json_decode($raw, true)
                    : ($raw ?? []);
                $current[$locale] = $value;
                $sharedUpdates[$column] = json_encode($current, JSON_UNESCAPED_UNICODE);
            } else {
                $sharedUpdates[$path] = $value;
            }
            $appliedPaths[] = $path;
        }

        if ($sharedUpdates !== []) {
            $service->updateQuietly($sharedUpdates);
        }

        // ── Type-specific detail columns ─────────────────────────────────────
        $appliedPaths = array_merge(
            $appliedPaths,
            match ($service->product_type) {
                ProductType::Rental => $this->applyRental($service, $proposed['type_specific'] ?? []),
                ProductType::Sale => $this->applySale($service, $proposed['type_specific'] ?? []),
                ProductType::Digital => $this->applyDigital($service, $proposed['type_specific'] ?? []),
            }
        );

        // ── Gallery ops noted here; actual media sync deferred to the queued ─
        // listener on ServiceChangeRequestApproved for safety (media-library).
        if (! empty($proposed['gallery_ops'])) {
            $appliedPaths[] = 'gallery_ops';
        }

        // ── Availability/pricing replay not yet implemented (Phase 1.5) ───────
        if (! empty($proposed['availability_windows'])) {
            $appliedPaths[] = 'availability_windows';
        }
        if (! empty($proposed['excluded_dates'])) {
            $appliedPaths[] = 'excluded_dates';
        }
        if (! empty($proposed['pricing_tiers'])) {
            $appliedPaths[] = 'pricing_tiers';
        }

        return $appliedPaths;
    }

    /** @param array<string, mixed> $typeSpecific */
    private function applyRental(Service $service, array $typeSpecific): array
    {
        return $this->applyDetailTable($service->rentalDetail(), $typeSpecific, 'service_rental_details.');
    }

    /** @param array<string, mixed> $typeSpecific */
    private function applySale(Service $service, array $typeSpecific): array
    {
        return $this->applyDetailTable($service->saleDetail(), $typeSpecific, 'service_sale_details.');
    }

    /** @param array<string, mixed> $typeSpecific */
    private function applyDigital(Service $service, array $typeSpecific): array
    {
        return $this->applyDetailTable($service->digitalDetail(), $typeSpecific, 'service_digital_details.');
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return list<string>
     */
    private function applyDetailTable(HasOne $detailRelation, array $fields, string $pathPrefix): array
    {
        if ($fields === []) {
            return [];
        }

        $detail = $detailRelation->lockForUpdate()->first();

        if ($detail === null) {
            return [];
        }

        $updates = [];
        $appliedPaths = [];

        foreach ($fields as $path => $value) {
            // Strip the "service_{type}_details." prefix stored in the field_path key
            $column = str_replace($pathPrefix, '', (string) $path);
            $updates[$column] = $value;
            $appliedPaths[] = $path;
        }

        if ($updates !== []) {
            $detail->updateQuietly($updates);
        }

        return $appliedPaths;
    }
}
