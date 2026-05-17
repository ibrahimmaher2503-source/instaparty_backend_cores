<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use App\Modules\Loyalty\Filament\Resources\LoyaltyRedemptionResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyRedemptionResource extends Resource
{
    protected static ?string $model = LoyaltyRedemption::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Loyalty';

    protected static ?int $navigationSort = 70;

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.redemptions');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.redemption.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.redemption.plural');
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
        return parent::getEloquentQuery()->with(['user', 'vendorProfile', 'booking']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('public_id')
                    ->label(__('loyalty.columns.public_id'))
                    ->copyable()
                    ->searchable()
                    ->limit(10),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('loyalty.columns.user'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('vendorProfile.business_name')
                    ->label(__('loyalty.columns.vendor'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'vendorProfile',
                        fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(business_name, '$.en')) LIKE ?", ["%{$search}%"])
                    )),
                Tables\Columns\TextColumn::make('booking.public_id')
                    ->label(__('loyalty.columns.booking'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('points_redeemed')
                    ->label(__('loyalty.columns.points_redeemed'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('loyalty.columns.amount'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('loyalty.columns.user'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(false),
                Tables\Filters\SelectFilter::make('vendor_profile_id')
                    ->label(__('loyalty.columns.vendor'))
                    ->relationship('vendorProfile', 'business_name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string =>
                        is_array($record->business_name)
                            ? ($record->business_name['en'] ?? $record->public_id)
                            : ($record->business_name ?? $record->public_id)
                    )
                    ->searchable()
                    ->preload(false),
                Tables\Filters\Filter::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('admin.common.from')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('admin.common.until')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyRedemptions::route('/'),
        ];
    }
}
