<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Filament\Resources\VendorCoverageAreaResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorCoverageAreaResource extends Resource
{
    protected static ?string $model = VendorCoverageArea::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.coverage_areas');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.coverage_area.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.coverage_area.plural');
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['vendorProfile', 'city']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendorProfile.business_name')
                    ->label(__('identity.columns.vendor'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'vendorProfile',
                        fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(business_name, '$.en')) LIKE ?", ["%{$search}%"])
                    )),
                Tables\Columns\TextColumn::make('city.name')
                    ->label(__('identity.columns.city_id'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—')),
                Tables\Columns\TextColumn::make('delivery_fee_minor')
                    ->label(__('identity.columns.delivery_fee'))
                    ->money('EGP', divideBy: 100),
                Tables\Columns\TextColumn::make('min_order_minor')
                    ->label(__('identity.columns.min_order'))
                    ->money('EGP', divideBy: 100),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorCoverageAreas::route('/'),
        ];
    }
}
