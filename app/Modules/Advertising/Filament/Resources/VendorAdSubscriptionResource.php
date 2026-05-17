<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Resources;

use App\Modules\Advertising\Domain\Enums\AdSubscriptionStatus;
use App\Modules\Advertising\Domain\Enums\PlacementType;
use App\Modules\Advertising\Domain\Models\VendorAdSubscription;
use App\Modules\Advertising\Filament\Resources\VendorAdSubscriptionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Modules\Advertising\Application\Actions\CancelAdSubscriptionAction;

class VendorAdSubscriptionResource extends Resource
{
    protected static ?string $model = VendorAdSubscription::class;
    protected static ?string $navigationIcon = 'heroicon-o-tv';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.advertising');
    }

    public static function getModelLabel(): string
    {
        return __('advertising.subscription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising.subscriptions');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('vendor_profile_id')
                ->label(__('advertising.vendor'))
                ->relationship('vendor', 'business_name->en')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('advertisement_package_id')
                ->label(__('advertising.package'))
                ->relationship('package', 'name->en')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('status')
                ->label(__('advertising.status'))
                ->options(collect(AdSubscriptionStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('public_id')
                    ->label('ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vendor.business_name')
                    ->label(__('advertising.vendor'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('package.name')
                    ->label(__('advertising.package'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('package.placement_type')
                    ->label(__('advertising.placement_type'))
                    ->badge()
                    ->color(fn (PlacementType $state): string => $state->color())
                    ->formatStateUsing(fn (PlacementType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('advertising.status'))
                    ->badge()
                    ->color(fn (AdSubscriptionStatus $state): string => $state->color())
                    ->formatStateUsing(fn (AdSubscriptionStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('advertising.starts_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('advertising.ends_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('impression_count')
                    ->label(__('advertising.impressions'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('click_count')
                    ->label(__('advertising.clicks'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_minor')
                    ->label(__('advertising.total'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(AdSubscriptionStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->label(__('advertising.status')),
                Tables\Filters\SelectFilter::make('placement_type')
                    ->options(collect(PlacementType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->relationship('package', 'placement_type')
                    ->label(__('advertising.placement_type')),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label(__('advertising.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VendorAdSubscription $record): bool => $record->status === AdSubscriptionStatus::Active)
                    ->action(function (VendorAdSubscription $record): void {
                        app(CancelAdSubscriptionAction::class)->execute($record, (int) auth()->id());
                        Notification::make()->title(__('advertising.cancelled_successfully'))->success()->send();
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorAdSubscriptions::route('/'),
        ];
    }
}
