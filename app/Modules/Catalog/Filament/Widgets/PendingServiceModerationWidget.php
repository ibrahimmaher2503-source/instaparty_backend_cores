<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Widgets;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingServiceModerationWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 20;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_rental_service') ?? false;
    }

    protected function getStats(): array
    {
        $stats = [];

        foreach (ProductType::cases() as $type) {
            $count = Service::query()
                ->where('status', ServiceStatus::PendingReview->value)
                ->where('product_type', $type->value)
                ->count();

            $stats[] = Stat::make(
                label: __("catalog::widgets.pending_service_moderation_{$type->value}_heading"),
                value: $count,
            )
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-shield-check')
                ->url(match ($type) {
                    ProductType::Rental => RentalServiceResource::getUrl('pending'),
                    ProductType::Sale => SaleServiceResource::getUrl('pending'),
                    ProductType::Digital => DigitalServiceResource::getUrl('pending'),
                });
        }

        return $stats;
    }
}
