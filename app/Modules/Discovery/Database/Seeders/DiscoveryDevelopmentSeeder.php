<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Database\Seeders;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\SavedSearch;
use App\Modules\Discovery\Domain\Models\SearchLog;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Discovery\Domain\Models\WishlistItem;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DiscoveryDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050310);

        DB::transaction(function (): void {
            $customerOne = User::query()->where('email', 'customer.one@instaparty.local')->firstOrFail();
            $customerTwo = User::query()->where('email', 'customer.two@instaparty.local')->firstOrFail();

            $services = Service::query()
                ->whereIn('slug', ['joy-castle-10x10', 'deluxe-birthday-cake', 'animated-birthday-invite'])
                ->get()
                ->keyBy('slug');

            $this->seedWishlist($customerOne, 'Birthday shortlist', [
                $services->get('joy-castle-10x10'),
                $services->get('deluxe-birthday-cake'),
            ]);

            $this->seedWishlist($customerTwo, 'Digital ideas', [
                $services->get('animated-birthday-invite'),
            ]);

            $this->seedSavedSearch($customerOne, 'Cairo birthday rentals', ['product_types' => ['rental'], 'city' => 'Nasr City', 'max_price_minor' => 300000]);
            $this->seedSavedSearch($customerTwo, 'Digital invitations', ['product_types' => ['digital'], 'locale' => 'ar']);

            $this->seedSearchLog($customerOne, 'bouncy castle cairo', 'en', ['product_types' => ['rental']], 2, $services->get('joy-castle-10x10'), '2026-05-19 10:00:00');
            $this->seedSearchLog($customerTwo, 'دعوة رقمية', 'ar', ['product_types' => ['digital']], 1, $services->get('animated-birthday-invite'), '2026-05-19 10:05:00');
        });
    }

    /**
     * @param  array<int, Service|null>  $services
     */
    private function seedWishlist(User $user, string $name, array $services): void
    {
        /** @var Wishlist $wishlist */
        $wishlist = $this->updateOrCreateFactoryModel(
            Wishlist::factory()->named($name)->make([
                'public_id' => $this->stablePublicId('wishlist:'.$user->email.':'.$name),
                'user_id' => $user->id,
                'name' => $name,
            ]),
            ['user_id' => $user->id, 'name' => $name],
        );

        foreach ($services as $service) {
            if (! $service instanceof Service) {
                continue;
            }

            $this->firstOrCreateFactoryModel(
                WishlistItem::factory()->make([
                    'wishlist_id' => $wishlist->id,
                    'service_id' => $service->id,
                ]),
                ['wishlist_id' => $wishlist->id, 'service_id' => $service->id],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function seedSavedSearch(User $user, string $label, array $filters): void
    {
        $this->updateOrCreateFactoryModel(
            SavedSearch::factory()->make([
                'user_id' => $user->id,
                'label' => $label,
                'filters' => $filters,
            ]),
            ['user_id' => $user->id, 'label' => $label],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function seedSearchLog(
        User $user,
        string $query,
        string $locale,
        array $filters,
        int $resultsCount,
        ?Service $clickedService,
        string $createdAt,
    ): void {
        $this->firstOrCreateFactoryModel(
            SearchLog::factory()->make([
                'user_id' => $user->id,
                'query' => $query,
                'locale' => $locale,
                'filters' => $filters,
                'results_count' => $resultsCount,
                'clicked_service_id' => $clickedService?->id,
                'created_at' => $createdAt,
            ]),
            ['user_id' => $user->id, 'query' => $query, 'created_at' => $createdAt],
        );
    }
}
