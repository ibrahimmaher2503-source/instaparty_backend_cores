<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Filament\Resources;

use App\Modules\Reviews\Domain\Models\ReviewModerationLog;
use App\Modules\Reviews\Filament\Resources\ReviewModerationLogResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewModerationLogResource extends Resource
{
    protected static ?string $model = ReviewModerationLog::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.moderation');
    }

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('reviews.nav.moderation_logs');
    }

    public static function getModelLabel(): string
    {
        return __('reviews.models.moderation_log.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reviews.models.moderation_log.plural');
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
                Tables\Columns\TextColumn::make('review_type')
                    ->label(__('reviews.columns.review_type'))
                    ->badge(),
                Tables\Columns\TextColumn::make('review_id')
                    ->label(__('reviews.columns.review_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('from_status')
                    ->label(__('reviews.columns.from_status'))
                    ->badge(),
                Tables\Columns\TextColumn::make('to_status')
                    ->label(__('reviews.columns.to_status'))
                    ->badge(),
                Tables\Columns\TextColumn::make('moderator_id')
                    ->label(__('reviews.columns.moderator'))
                    ->sortable(),
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
            'index' => Pages\ListReviewModerationLogs::route('/'),
        ];
    }
}
