<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingStateTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingStateTransition>
 */
class BookingStateTransitionFactory extends Factory
{
    protected $model = BookingStateTransition::class;

    public function definition(): array
    {
        return [
            'transitionable_type' => Booking::class,
            'transitionable_id' => Booking::factory(),
            'from_state' => 'draft',
            'to_state' => 'submitted',
            'triggered_by' => null,
            'trigger_kind' => 'customer',
            'context' => [],
            'created_at' => now(),
        ];
    }

    public function system(): static
    {
        return $this->state(['trigger_kind' => 'system']);
    }

    public function customer(): static
    {
        return $this->state(['trigger_kind' => 'customer']);
    }

    public function vendor(): static
    {
        return $this->state(['trigger_kind' => 'vendor']);
    }

    public function admin(): static
    {
        return $this->state(['trigger_kind' => 'admin']);
    }
}
