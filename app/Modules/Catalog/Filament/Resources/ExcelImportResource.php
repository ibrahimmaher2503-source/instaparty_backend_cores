<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Filament\Resources\ExcelImportResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExcelImportResource extends Resource
{
    protected static ?string $model = ExcelImport::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.services');
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.excel_imports');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.excel_import.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.excel_import.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('errors')->with(['vendor']);
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
                Tables\Columns\TextColumn::make('public_id')
                    ->label(__('catalog.public_id'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('vendor.business_name')
                    ->label(__('catalog.vendor'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'vendor',
                        fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(business_name, '$.en')) LIKE ?", ["%{$search}%"])
                    )),
                Tables\Columns\TextColumn::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->badge()
                    ->formatStateUsing(fn (ProductType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('catalog.status_label'))
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label(__('catalog.original_filename'))
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_rows')
                    ->label(__('catalog.total_rows'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('imported_rows')
                    ->label(__('catalog.imported_rows_count'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('errors_count')
                    ->label(__('catalog.error_rows'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->options(collect(ProductType::cases())->mapWithKeys(fn (ProductType $type): array => [$type->value => $type->label()])),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('catalog.status_label'))
                    ->options([
                        'pending' => __('catalog.import_status.pending'),
                        'completed' => __('catalog.import_status.completed'),
                        'failed' => __('catalog.import_status.failed'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExcelImports::route('/'),
        ];
    }
}
