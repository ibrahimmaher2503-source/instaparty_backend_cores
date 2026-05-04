<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Filament\Resources\VendorApprovedProductTypeResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorApprovedProductTypeResource extends Resource
{
    protected static ?string $model = VendorApprovedProductType::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.approved_product_types');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.approved_product_type.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.approved_product_type.plural');
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
                Tables\Columns\TextColumn::make('product_type')
                    ->label(__('identity.columns.product_type'))
                    ->badge()
                    ->formatStateUsing(fn (ProductType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('approved_at')
                    ->label(__('identity.columns.approved_at'))
                    ->dateTime(),
                Tables\Columns\TextColumn::make('revoked_at')
                    ->label(__('identity.columns.revoked_at'))
                    ->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorApprovedProductTypes::route('/'),
        ];
    }
}
