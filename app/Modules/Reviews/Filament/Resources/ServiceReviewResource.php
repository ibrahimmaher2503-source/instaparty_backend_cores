<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Filament\Resources;

use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewLocale;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Filament\Resources\ServiceReviewResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceReviewResource extends Resource
{
    protected static ?string $model = ServiceReview::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.moderation');
    }

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('reviews.nav.service_reviews');
    }

    public static function getModelLabel(): string
    {
        return __('reviews.models.service_review.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reviews.models.service_review.plural');
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
                    ->label(__('reviews.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('service_id')
                    ->label(__('reviews.columns.service'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking_item_id')
                    ->label(__('reviews.columns.booking_item'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->label(__('reviews.columns.reviewer'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label(__('reviews.columns.rating'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('locale')
                    ->label(__('reviews.columns.locale'))
                    ->badge()
                    ->formatStateUsing(fn (ReviewLocale $state): string => $state->value),
                Tables\Columns\TextColumn::make('moderation_status')
                    ->label(__('reviews.columns.moderation_status'))
                    ->badge()
                    ->formatStateUsing(fn (ModerationStatus $state): string => __('reviews.moderation_status.'.$state->value)),
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
            'index' => Pages\ListServiceReviews::route('/'),
        ];
    }
}
