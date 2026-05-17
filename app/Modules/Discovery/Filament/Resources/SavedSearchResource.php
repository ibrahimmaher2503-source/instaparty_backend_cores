<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources;

use App\Modules\Discovery\Domain\Models\SavedSearch;
use App\Modules\Discovery\Filament\Resources\SavedSearchResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SavedSearchResource extends Resource
{
    protected static ?string $model = SavedSearch::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.discovery');
    }

    protected static ?string $navigationIcon = 'heroicon-o-bookmark';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('discovery.nav.saved_searches');
    }

    public static function getModelLabel(): string
    {
        return __('discovery.models.saved_search.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discovery.models.saved_search.plural');
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
        return parent::getEloquentQuery()->with(['user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('discovery.columns.user_id'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->label(__('discovery.columns.label'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('filters')
                    ->label(__('discovery.columns.filters'))
                    ->limit(60),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSavedSearches::route('/'),
        ];
    }
}
