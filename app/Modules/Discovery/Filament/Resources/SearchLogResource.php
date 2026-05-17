<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources;

use App\Modules\Discovery\Domain\Models\SearchLog;
use App\Modules\Discovery\Filament\Resources\SearchLogResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SearchLogResource extends Resource
{
    protected static ?string $model = SearchLog::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.discovery');
    }

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('discovery.nav.search_logs');
    }

    public static function getModelLabel(): string
    {
        return __('discovery.models.search_log.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discovery.models.search_log.plural');
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
        return parent::getEloquentQuery()->with(['user', 'clickedService']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('discovery.columns.user_id'))
                    ->searchable()
                    ->default('Guest'),
                Tables\Columns\TextColumn::make('query')
                    ->label(__('discovery.columns.query'))
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('locale')
                    ->label(__('discovery.columns.locale'))
                    ->badge(),
                Tables\Columns\TextColumn::make('results_count')
                    ->label(__('discovery.columns.results_count'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('clickedService.name')
                    ->label(__('discovery.columns.clicked_service_id'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—'))
                    ->default('—'),
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
            'index' => Pages\ListSearchLogs::route('/'),
        ];
    }
}
