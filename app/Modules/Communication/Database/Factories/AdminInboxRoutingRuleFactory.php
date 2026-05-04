<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Factories;

use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AdminInboxRoutingRuleFactory extends Factory
{
    protected $model = AdminInboxRoutingRule::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::ulid()->toBase32(),
            'event_key' => $this->faker->randomElement([
                'vendor.registered',
                'payment.failed',
                'booking.stalled',
                'chat.flagged',
                'withdrawal.requested',
                'service.submitted_for_review',
            ]),
            'severity' => $this->faker->randomElement(AdminInboxSeverity::cases())->value,
            'route_to_role_id' => null,
            'route_to_admin_id' => null,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function forRole(int $roleId): static
    {
        return $this->state(['route_to_role_id' => $roleId, 'route_to_admin_id' => null]);
    }

    public function forAdmin(int $adminId): static
    {
        return $this->state(['route_to_admin_id' => $adminId, 'route_to_role_id' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
