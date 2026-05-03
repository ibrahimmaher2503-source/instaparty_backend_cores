<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Providers;

use App\Modules\Reviews\Application\Listeners\RecomputeRatingOnApproval;
use App\Modules\Reviews\Domain\Contracts\ReviewModerationLogRepository;
use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Reviews\Domain\Events\ReviewApproved;
use App\Modules\Reviews\Domain\Events\ReviewHidden;
use App\Modules\Reviews\Domain\Events\ReviewRejected;
use App\Modules\Reviews\Domain\Events\ReviewSelfDeleted;
use App\Modules\Reviews\Infrastructure\Repositories\EloquentReviewModerationLogRepository;
use App\Modules\Reviews\Infrastructure\Repositories\EloquentServiceReviewRepository;
use App\Modules\Reviews\Infrastructure\Repositories\EloquentVendorReviewRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ReviewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ServiceReviewRepository::class, EloquentServiceReviewRepository::class);
        $this->app->singleton(VendorReviewRepository::class, EloquentVendorReviewRepository::class);
        $this->app->singleton(ReviewModerationLogRepository::class, EloquentReviewModerationLogRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'reviews');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'reviews');

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../Routes/customer.php');

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../Routes/public.php');

        Event::listen(ReviewApproved::class, RecomputeRatingOnApproval::class);
        Event::listen(ReviewRejected::class, RecomputeRatingOnApproval::class);
        Event::listen(ReviewHidden::class, RecomputeRatingOnApproval::class);
        Event::listen(ReviewSelfDeleted::class, RecomputeRatingOnApproval::class);
    }
}
