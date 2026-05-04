<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\UserDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserDevice>
 */
class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => $this->faker->randomElement(['ios', 'android', 'web']),
            'fcm_token' => 'fcm_'.Str::lower(Str::random(32)),
            'device_id' => 'device_'.Str::lower(Str::random(20)),
            'last_seen_at' => now(),
        ];
    }

    public function ios(): static
    {
        return $this->state(['platform' => 'ios']);
    }

    public function android(): static
    {
        return $this->state(['platform' => 'android']);
    }

    public function web(): static
    {
        return $this->state(['platform' => 'web']);
    }
}
