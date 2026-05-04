<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Domain\Enums\DayOfWeek;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Filament\Resources\VendorBusinessHourResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorBusinessHourResource extends Resource
{
    protected static ?string $model = VendorBusinessHour::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.business_hours');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.business_hour.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.business_hour.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor_profile_id')
                    ->label(__('identity.columns.vendor'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label(__('identity.columns.day_of_week'))
                    ->badge()
                    ->formatStateUsing(fn (DayOfWeek $state): string => __('identity.day_of_week.'.$state->value)),
                Tables\Columns\TextColumn::make('opens_at')
                    ->label(__('identity.columns.opens_at')),
                Tables\Columns\TextColumn::make('closes_at')
                    ->label(__('identity.columns.closes_at')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorBusinessHours::route('/'),
        ];
    }
}
