<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Database\Factories;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Discovery\Domain\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
{
    protected $model = WishlistItem::class;

    public function definition(): array
    {
        return [
            'wishlist_id' => Wishlist::factory(),
            'service_id' => Service::factory()->published(),
        ];
    }
}
