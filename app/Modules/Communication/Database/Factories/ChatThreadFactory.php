<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Factories;

use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatThread>
 */
class ChatThreadFactory extends Factory
{
    protected $model = ChatThread::class;

    public function definition(): array
    {
        return [
            'public_id'           => Str::ulid()->toBase32(),
            'firestore_thread_id' => (string) Str::ulid(),
            'customer_id'         => User::factory(),
            'vendor_profile_id'   => VendorProfile::factory(),
            'booking_id'          => null,
            'status'              => 'open',
            'locked_at'           => null,
            'frozen_at'           => null,
            'frozen_by'           => null,
        ];
    }

    public function frozen(?int $byUserId = null): static
    {
        return $this->state(fn (array $attrs) => [
            'status'    => 'locked',
            'frozen_at' => now(),
            'frozen_by' => $byUserId ?? User::factory(),
        ]);
    }
}
