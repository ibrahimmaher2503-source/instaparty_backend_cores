<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ArchivedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\ChangesRequestedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PendingReviewState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\RejectedState;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $productType = $this->faker->randomElement(ProductType::cases());
        $name = $this->faker->words(3, true);

        return [
            'public_id' => (string) Str::ulid(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'category_id' => Category::factory()->state([
                'allowed_product_types' => [$productType->value],
            ]),
            'product_type' => $productType,
            'name' => ['en' => $name, 'ar' => $name.' (ar)'],
            'short_description' => ['en' => $this->faker->sentence(), 'ar' => $this->faker->sentence()],
            'long_description' => ['en' => $this->faker->paragraph(), 'ar' => $this->faker->paragraph()],
            'slug' => Str::slug($name.'-'.$this->faker->unique()->numberBetween(100, 999)),
            'status' => DraftState::$name,
            'base_price_minor' => $this->faker->numberBetween(10000, 500000),
            'base_price_currency' => 'EGP',
            'is_featured' => false,
        ];
    }

    public function rental(): static
    {
        return $this->state([
            'product_type' => ProductType::Rental,
            'category_id' => Category::factory()->state([
                'allowed_product_types' => [ProductType::Rental->value],
            ]),
        ]);
    }

    public function sale(): static
    {
        return $this->state([
            'product_type' => ProductType::Sale,
            'category_id' => Category::factory()->state([
                'allowed_product_types' => [ProductType::Sale->value],
            ]),
        ]);
    }

    public function digital(): static
    {
        return $this->state([
            'product_type' => ProductType::Digital,
            'category_id' => Category::factory()->state([
                'allowed_product_types' => [ProductType::Digital->value],
            ]),
        ]);
    }

    public function published(): static
    {
        return $this->state([
            'status' => PublishedState::$name,
            'moderated_at' => now(),
            'moderated_by' => User::factory(),
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(['status' => PendingReviewState::$name]);
    }

    public function changesRequested(): static
    {
        return $this->state(['status' => ChangesRequestedState::$name]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => RejectedState::$name,
            'moderation_notes' => [
                'en' => 'Rejected during moderation.',
                'ar' => 'Rejected during moderation.',
            ],
            'moderated_at' => now(),
            'moderated_by' => User::factory(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(['status' => ArchivedState::$name]);
    }

    public function moderatedBy(User|int $moderator): static
    {
        return $this->state([
            'moderated_by' => $moderator instanceof User ? $moderator->id : $moderator,
            'moderated_at' => now(),
        ]);
    }
}
