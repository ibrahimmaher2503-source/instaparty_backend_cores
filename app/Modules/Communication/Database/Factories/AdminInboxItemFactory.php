<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Factories;

use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AdminInboxItemFactory extends Factory
{
    protected $model = AdminInboxItem::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::ulid()->toBase32(),
            'admin_id' => User::factory(),
            'source_type' => 'vendor_profile',
            'source_id' => $this->faker->numberBetween(1, 1000),
            'severity' => AdminInboxSeverity::Info->value,
            'title' => ['en' => 'New alert', 'ar' => 'تنبيه جديد'],
            'body' => ['en' => 'Alert body.', 'ar' => 'نص التنبيه.'],
            'status' => AdminInboxStatus::Unread->value,
            'snoozed_until' => null,
            'assigned_to_admin_id' => null,
        ];
    }

    public function unread(): static
    {
        return $this->state(['status' => AdminInboxStatus::Unread->value]);
    }

    public function snoozed(int $hoursFromNow = 4): static
    {
        return $this->state([
            'status' => AdminInboxStatus::Snoozed->value,
            'snoozed_until' => now()->addHours($hoursFromNow),
        ]);
    }

    public function snoozedExpired(): static
    {
        return $this->state([
            'status' => AdminInboxStatus::Snoozed->value,
            'snoozed_until' => now()->subHour(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(['status' => AdminInboxStatus::Resolved->value]);
    }
}
