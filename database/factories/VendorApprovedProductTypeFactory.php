<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorApprovedProductType>
 */
class VendorApprovedProductTypeFactory extends Factory
{
    protected $model = VendorApprovedProductType::class;

    public function definition(): array
    {
        return [
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'product_type' => fake()->randomElement(ProductType::cases())->value,
            'approved_at' => now(),
            'approved_by' => null,
            'revoked_at' => null,
            'revoked_by' => null,
            'revoke_reason' => null,
        ];
    }

    public function forType(ProductType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'product_type' => $type->value,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
            'revoke_reason' => ['reason' => 'revoked_manually'],
        ]);
    }
}
