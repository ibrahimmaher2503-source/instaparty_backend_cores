<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Providers;

use App\Modules\Catalog\Domain\Events\ServiceArchived;
use App\Modules\Catalog\Domain\Events\ServicePublished;
use App\Modules\Discovery\Application\Listeners\LogSearchQueryListener;
use App\Modules\Discovery\Application\Listeners\ServiceIndexListener;
use App\Modules\Discovery\Domain\Contracts\SearchRepository;
use App\Modules\Discovery\Domain\Events\ServiceSearchPerformed;
use App\Modules\Discovery\Infrastructure\Repositories\EloquentSearchRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SearchRepository::class,
            EloquentSearchRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'discovery');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/customer.php');

        Event::listen(ServicePublished::class, [ServiceIndexListener::class, 'handlePublished']);
        Event::listen(ServiceArchived::class, [ServiceIndexListener::class, 'handleArchived']);
        Event::listen(ServiceSearchPerformed::class, LogSearchQueryListener::class);

        $this->configureMeilisearchIndex();
    }

    private function configureMeilisearchIndex(): void
    {
        try {
            /** @var \Meilisearch\Client $client */
            $client = app(\Meilisearch\Client::class);

            $indexName = (new \App\Modules\Catalog\Domain\Models\Service())->searchableAs();

            $client->index($indexName)->updateSettings([
                'searchableAttributes' => [
                    'name_ar',
                    'name_en',
                    'short_description_ar',
                    'short_description_en',
                ],
                'filterableAttributes' => [
                    'product_type',
                    'category_id',
                    'occasion_ids',
                    'vendor_id',
                    'is_active',
                    'price_minor',
                    'currency',
                    'requires_electricity',
                    'requires_outdoor_space',
                    'is_perishable',
                    'allows_customization',
                    'delivery_method',
                    'has_expiry',
                ],
                'sortableAttributes' => [
                    'price_minor',
                    'vendor_rating',
                    'rating_avg',
                ],
            ]);
        } catch (\Throwable) {
            // Meilisearch unavailable — skip index configuration silently.
        }
    }
}
