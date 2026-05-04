<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingSnapshot>
 */
class BookingSnapshotFactory extends Factory
{
    protected $model = BookingSnapshot::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'booking_id' => Booking::factory(),
            'version' => 1,
            'snapshot' => [
                'status' => 'draft',
            ],
            'trigger_kind' => 'booking_created',
            'trigger_reference_type' => null,
            'trigger_reference_id' => null,
            'triggered_by' => null,
            'created_at' => now(),
        ];
    }

    public function version(int $version): static
    {
        return $this->state(['version' => $version]);
    }
}
