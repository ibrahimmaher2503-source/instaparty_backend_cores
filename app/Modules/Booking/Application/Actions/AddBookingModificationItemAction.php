<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\AddBookingModificationItemDTO;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AddBookingModificationItemAction
{
    public function __construct(
        private readonly PreventModificationAfterPaymentAction $guard,
        private readonly RecalculateBookingModificationTotalsAction $recalculate,
    ) {}

    public function execute(AddBookingModificationItemDTO $dto): BookingModificationItem
    {
        return DB::transaction(function () use ($dto): BookingModificationItem {
            /** @var BookingModification $modification */
            $modification = BookingModification::query()
                ->where('id', $dto->bookingModificationId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $modification->status !== ModificationStatus::Draft,
                Response::HTTP_CONFLICT,
                'Modification is not in draft status',
            );

            $bookingVendor = $modification->bookingVendor()->firstOrFail();

            abort_if(
                $bookingVendor->vendor_profile_id !== $dto->vendorProfileId,
                Response::HTTP_FORBIDDEN,
            );

            $this->guard->execute($bookingVendor);

            $targetItem = null;
            if ($dto->targetBookingItemId !== null) {
                /** @var BookingItem|null $targetItem */
                $targetItem = BookingItem::query()
                    ->where('id', $dto->targetBookingItemId)
                    ->where('booking_vendor_id', $bookingVendor->id)
                    ->first();

                abort_if(
                    $targetItem === null,
                    Response::HTTP_NOT_FOUND,
                    'Target booking item does not belong to this booking vendor',
                );
            }

            $effectiveChangeKind = $this->resolveEffectiveChangeKind($dto, $targetItem);
            $productType = $this->resolveProductType($dto, $targetItem);

            $original = $targetItem !== null
                ? $this->snapshotOriginalValue($targetItem)
                : null;

            $payload = $this->buildPayload($dto, $effectiveChangeKind, $productType, $targetItem, $original);

            $item = BookingModificationItem::create([
                'booking_modification_id' => $modification->id,
                'target_booking_item_id' => $targetItem?->id,
                'change_kind' => $effectiveChangeKind,
                'payload' => $payload,
            ]);

            $this->recalculate->execute($modification->fresh(['items']));

            return $item;
        });
    }

    private function resolveEffectiveChangeKind(
        AddBookingModificationItemDTO $dto,
        ?BookingItem $targetItem,
    ): ModificationChangeKind {
        if ($dto->changeKind === ModificationChangeKind::Update
            && $targetItem !== null
            && array_key_exists('quantity', $dto->payload)
            && (int) $dto->payload['quantity'] <= 0) {
            return ModificationChangeKind::Remove;
        }

        return $dto->changeKind;
    }

    private function resolveProductType(
        AddBookingModificationItemDTO $dto,
        ?BookingItem $targetItem,
    ): ProductType {
        if ($targetItem !== null) {
            return $targetItem->product_type;
        }

        $raw = $dto->payload['product_type'] ?? null;

        if ($raw instanceof ProductType) {
            return $raw;
        }

        if (is_string($raw)) {
            return ProductType::from($raw);
        }

        if ($dto->payload['service_id'] ?? null) {
            $service = Service::query()->find((int) $dto->payload['service_id']);
            if ($service !== null) {
                return $service->product_type;
            }
        }

        abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Could not resolve product type for new item');
    }

    /**
     * @param  array<string,mixed>|null  $original
     * @return array<string,mixed>
     */
    private function buildPayload(
        AddBookingModificationItemDTO $dto,
        ModificationChangeKind $effectiveChangeKind,
        ProductType $productType,
        ?BookingItem $targetItem,
        ?array $original,
    ): array {
        $proposed = $dto->payload;
        $proposed['product_type'] = $productType->value;

        $proposed = match ($productType) {
            ProductType::Rental => $this->normalizeRentalPayload($proposed),
            ProductType::Sale => $this->normalizeSalePayload($proposed),
            ProductType::Digital => $this->normalizeDigitalPayload($proposed),
        };

        $priceDelta = $this->computePriceDelta($effectiveChangeKind, $proposed, $original);
        $quantityDelta = $this->computeQuantityDelta($effectiveChangeKind, $proposed, $original);
        $timeDelta = $this->computeTimeDelta($productType, $proposed, $original);

        $flat = match ($effectiveChangeKind) {
            ModificationChangeKind::Remove => [],
            default => array_filter([
                'service_id' => $proposed['service_id'] ?? ($original['service_id'] ?? null),
                'product_type' => $productType->value,
                'unit_price_minor' => $proposed['unit_price_minor'] ?? ($original['unit_price_minor'] ?? null),
                'unit_price_currency' => $proposed['unit_price_currency'] ?? ($original['unit_price_currency'] ?? null),
                'quantity' => $proposed['quantity'] ?? ($original['quantity'] ?? null),
                'effective_starts_at' => $proposed['effective_starts_at'] ?? ($original['effective_starts_at'] ?? null),
                'effective_ends_at' => $proposed['effective_ends_at'] ?? ($original['effective_ends_at'] ?? null),
                'customization_data' => $proposed['customization_data'] ?? ($original['customization_data'] ?? null),
            ], static fn ($v) => $v !== null),
        };

        return array_merge($flat, [
            'change_type' => $dto->changeType->value,
            'original_value' => $original,
            'proposed_value' => $effectiveChangeKind === ModificationChangeKind::Remove ? null : $proposed,
            'price_delta_minor' => $priceDelta,
            'quantity_delta' => $quantityDelta,
            'time_delta' => $timeDelta,
            'vendor_note' => $dto->payload['vendor_note'] ?? null,
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalizeRentalPayload(array $payload): array
    {
        return $payload;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalizeSalePayload(array $payload): array
    {
        unset($payload['effective_starts_at'], $payload['effective_ends_at']);

        return $payload;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalizeDigitalPayload(array $payload): array
    {
        unset($payload['effective_starts_at'], $payload['effective_ends_at']);

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshotOriginalValue(BookingItem $item): array
    {
        return [
            'service_id' => $item->service_id,
            'product_type' => $item->product_type->value,
            'unit_price_minor' => $item->unit_price_minor,
            'unit_price_currency' => $item->unit_price_currency,
            'quantity' => $item->quantity,
            'effective_starts_at' => $item->effective_starts_at?->toIso8601String(),
            'effective_ends_at' => $item->effective_ends_at?->toIso8601String(),
            'customization_data' => $item->customization_data,
            'name_snapshot' => $item->name_snapshot,
        ];
    }

    /**
     * @param  array<string,mixed>  $proposed
     * @param  array<string,mixed>|null  $original
     */
    private function computePriceDelta(
        ModificationChangeKind $kind,
        array $proposed,
        ?array $original,
    ): int {
        return match ($kind) {
            ModificationChangeKind::Add => (int) ($proposed['unit_price_minor'] ?? 0)
                * (int) ($proposed['quantity'] ?? 1),
            ModificationChangeKind::Remove => -1 * (int) (($original['unit_price_minor'] ?? 0))
                * (int) (($original['quantity'] ?? 1)),
            ModificationChangeKind::Update => $this->computeUpdatePriceDelta($proposed, $original),
        };
    }

    /**
     * @param  array<string,mixed>  $proposed
     * @param  array<string,mixed>|null  $original
     */
    private function computeUpdatePriceDelta(array $proposed, ?array $original): int
    {
        if ($original === null) {
            return 0;
        }

        $newPrice = isset($proposed['unit_price_minor'])
            ? (int) $proposed['unit_price_minor']
            : (int) ($original['unit_price_minor'] ?? 0);

        $newQty = isset($proposed['quantity'])
            ? (int) $proposed['quantity']
            : (int) ($original['quantity'] ?? 0);

        $oldPrice = (int) ($original['unit_price_minor'] ?? 0);
        $oldQty = (int) ($original['quantity'] ?? 0);

        return ($newPrice * $newQty) - ($oldPrice * $oldQty);
    }

    /**
     * @param  array<string,mixed>  $proposed
     * @param  array<string,mixed>|null  $original
     */
    private function computeQuantityDelta(
        ModificationChangeKind $kind,
        array $proposed,
        ?array $original,
    ): int {
        return match ($kind) {
            ModificationChangeKind::Add => (int) ($proposed['quantity'] ?? 1),
            ModificationChangeKind::Remove => -1 * (int) (($original['quantity'] ?? 0)),
            ModificationChangeKind::Update => isset($proposed['quantity'])
                ? (int) $proposed['quantity'] - (int) ($original['quantity'] ?? 0)
                : 0,
        };
    }

    /**
     * @param  array<string,mixed>  $proposed
     * @param  array<string,mixed>|null  $original
     */
    private function computeTimeDelta(
        ProductType $productType,
        array $proposed,
        ?array $original,
    ): ?string {
        if ($productType !== ProductType::Rental) {
            return null;
        }

        if ($original === null
            || ! isset($original['effective_starts_at'], $proposed['effective_starts_at'])) {
            return null;
        }

        try {
            $before = CarbonImmutable::parse((string) $original['effective_starts_at']);
            $after = CarbonImmutable::parse((string) $proposed['effective_starts_at']);
        } catch (\Throwable) {
            return null;
        }

        if ($before->equalTo($after)) {
            return null;
        }

        return $before->diff($after)->spec();
    }
}
