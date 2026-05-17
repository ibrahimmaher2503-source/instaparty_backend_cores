<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\ServiceFieldDiff;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\MaterialFieldRegistry;

class DetectMaterialServiceChangesAction
{
    public function __construct(
        private readonly MaterialFieldRegistry $registry,
    ) {}

    /**
     * Compares the proposed payload against the current service state and returns a diff.
     *
     * @param  array<string, mixed>  $proposedPayload  Validated request payload
     */
    public function execute(Service $service, array $proposedPayload): ServiceFieldDiff
    {
        $type = $service->product_type;
        $items = [];

        // ── Flat field comparison ────────────────────────────────────────────
        $flatFields = $this->flattenPayload($proposedPayload);

        foreach ($flatFields as $fieldPath => $afterValue) {
            // Only compare fields that appear in the proposed payload
            $beforeValue = $this->resolveBeforeValue($service, $fieldPath);

            if ($beforeValue === $afterValue) {
                continue;
            }

            $isMaterial = $this->registry->isMaterial($fieldPath, $type);
            $classification = $this->registry->classifyField($fieldPath, $type);

            $items[] = [
                'field_path' => $fieldPath,
                'field_classification' => $classification->value,
                'before_value' => $beforeValue,
                'after_value' => $afterValue,
                'is_material' => $isMaterial,
            ];
        }

        // ── gallery_ops ──────────────────────────────────────────────────────
        if (isset($proposedPayload['gallery_ops']) && is_array($proposedPayload['gallery_ops']) && count($proposedPayload['gallery_ops']) > 0) {
            $items[] = [
                'field_path' => 'gallery_ops',
                'field_classification' => 'media',
                'before_value' => null,
                'after_value' => $proposedPayload['gallery_ops'],
                'is_material' => true,
            ];
        }

        // ── availability_windows, excluded_dates ─────────────────────────────
        foreach (['availability_windows', 'excluded_dates'] as $key) {
            if (isset($proposedPayload[$key])) {
                $items[] = [
                    'field_path' => $key,
                    'field_classification' => 'availability',
                    'before_value' => null,
                    'after_value' => $proposedPayload[$key],
                    'is_material' => true,
                ];
            }
        }

        // ── pricing_tiers ────────────────────────────────────────────────────
        if (isset($proposedPayload['pricing_tiers'])) {
            $items[] = [
                'field_path' => 'pricing_tiers',
                'field_classification' => 'pricing_tier',
                'before_value' => null,
                'after_value' => $proposedPayload['pricing_tiers'],
                'is_material' => true,
            ];
        }

        return new ServiceFieldDiff($items, $type);
    }

    /**
     * Flattens a payload to dot-notation field paths. Does NOT expand arrays like gallery_ops (handled separately).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flattenPayload(array $payload): array
    {
        $flat = [];
        $skip = ['gallery_ops', 'availability_windows', 'excluded_dates', 'pricing_tiers', 'details', 'vendor_note'];

        foreach ($payload as $key => $value) {
            if (in_array($key, $skip, true)) {
                continue;
            }

            if (is_array($value) && ! array_is_list($value)) {
                // Translatable object like ['en' => '...', 'ar' => '...']
                foreach ($value as $locale => $localeValue) {
                    $flat["{$key}.{$locale}"] = $localeValue;
                }
            } else {
                $flat[$key] = $value;
            }
        }

        // Expand `details` sub-object with type-specific prefix
        if (isset($payload['details']) && is_array($payload['details'])) {
            // The detail table prefix depends on product_type and is resolved by the caller
            // We use a generic 'details.*' pass and let classifyField handle it
            foreach ($payload['details'] as $detailKey => $detailValue) {
                if (is_array($detailValue) && ! array_is_list($detailValue)) {
                    foreach ($detailValue as $locale => $localeValue) {
                        $flat["details.{$detailKey}.{$locale}"] = $localeValue;
                    }
                } else {
                    $flat["details.{$detailKey}"] = $detailValue;
                }
            }
        }

        return $flat;
    }

    /**
     * Resolves the current live value for a dot-notation field path.
     */
    private function resolveBeforeValue(Service $service, string $fieldPath): mixed
    {
        if (str_contains($fieldPath, '.') && ! str_starts_with($fieldPath, 'service_')) {
            [$root, $key] = explode('.', $fieldPath, 2);
            $rootValue = $service->{$root};
            if (is_array($rootValue)) {
                return $rootValue[$key] ?? null;
            }
        }

        return $service->{$fieldPath} ?? null;
    }
}
