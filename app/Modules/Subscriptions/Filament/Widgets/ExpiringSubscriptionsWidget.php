<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Widgets;

use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ExpiringSubscriptionsWidget extends TableWidget
{
    protected static ?string $heading = null;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('subscriptions.expiring_soon');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VendorSubscription::query()
                    ->with(['vendor', 'plan'])
                    ->where('status', 'active')
                    ->whereBetween('current_period_end', [now(), now()->addDays(30)])
                    ->orderBy('current_period_end')
            )
            ->columns([
                Tables\Columns\TextColumn::make('vendor.business_name')
                    ->label(__('subscriptions.vendor'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('subscriptions.plan')),
                Tables\Columns\TextColumn::make('current_period_end')
                    ->label(__('subscriptions.expires_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('remind')
                    ->label(__('subscriptions.send_renewal_reminder'))
                    ->icon('heroicon-o-bell')
                    ->action(function (VendorSubscription $record): void {
                        Notification::make()
                            ->title(__('subscriptions.renewal_reminder_sent'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
