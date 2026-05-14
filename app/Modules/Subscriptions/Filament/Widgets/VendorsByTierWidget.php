<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Widgets;

use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Filament\Widgets\ChartWidget;

class VendorsByTierWidget extends ChartWidget
{
    protected static ?string $heading = 'Vendors by Subscription Tier';

    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $data = VendorSubscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->selectRaw('subscription_plan_id, COUNT(*) as count')
            ->groupBy('subscription_plan_id')
            ->with('plan:id,plan_code')
            ->get()
            ->map(fn ($row) => [
                'tier' => $row->plan->plan_code,
                'count' => $row->count,
            ])
            ->sortBy('tier')
            ->values();

        if ($data->isEmpty()) {
            return [
                'datasets' => [['label' => 'Vendors', 'data' => []]],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active Vendors',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => [
                        '#6B7280',
                        '#0EA5E9',
                        '#F59E0B',
                        '#10B981',
                    ],
                ],
            ],
            'labels' => $data->pluck('tier')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
