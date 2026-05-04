<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingModificationItem>
 */
class BookingModificationItemFactory extends Factory
{
    protected $model = BookingModificationItem::class;

    public function definition(): array
    {
        return [
            'booking_modification_id' => BookingModification::factory(),
            'target_booking_item_id' => null,
            'change_kind' => ModificationChangeKind::Update,
            'payload' => [
                'unit_price_minor' => 12000,
            ],
            'created_at' => now(),
        ];
    }

    public function withTargetItem(): static
    {
        return $this->state([
            'target_booking_item_id' => BookingItem::factory(),
        ]);
    }
}
