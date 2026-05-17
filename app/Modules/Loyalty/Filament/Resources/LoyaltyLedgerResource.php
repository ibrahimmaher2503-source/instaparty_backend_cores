<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Application\Actions\AdjustLoyaltyBalanceAction;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Filament\Resources\LoyaltyLedgerResource\Pages;
use DomainException;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyLedgerResource extends Resource
{
    protected static ?string $model = LoyaltyLedgerEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Loyalty';

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.ledger');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.ledger_entry.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.ledger_entry.plural');
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
        return parent::getEloquentQuery()->with(['user', 'vendorProfile']);
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
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—')),
                Tables\Columns\TextColumn::make('direction')
                    ->label(__('loyalty.columns.direction'))
                    ->badge()
                    ->color(fn (LedgerDirection $state): string => match ($state) {
                        LedgerDirection::Earn => 'success',
                        LedgerDirection::Redeem => 'warning',
                        LedgerDirection::Expire => 'gray',
                        LedgerDirection::Adjust => 'info',
                    })
                    ->formatStateUsing(fn (LedgerDirection $state) => $state->label()),
                Tables\Columns\TextColumn::make('points')
                    ->label(__('loyalty.columns.points'))
                    ->formatStateUsing(function ($state, LoyaltyLedgerEntry $record): string {
                        $sign = match (true) {
                            $record->direction === LedgerDirection::Earn => '+',
                            $record->direction === LedgerDirection::Adjust && (int) $record->points > 0 => '+',
                            $record->direction === LedgerDirection::Redeem,
                            $record->direction === LedgerDirection::Expire => '−',
                            default => '−',
                        };

                        return $sign.(string) $state;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label(__('loyalty.columns.balance_after'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_type')
                    ->label(__('loyalty.columns.reference_type'))
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reference_id')
                    ->label(__('loyalty.columns.reference_id'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label(__('loyalty.columns.reason'))
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state[app()->getLocale()] ?? $state['en'] ?? '')
                        : (string) ($state ?? ''))
                    ->limit(60)
                    ->wrap(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('loyalty.columns.expires_at'))
                    ->dateTime()
                    ->default(__('loyalty.no_expiry'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('direction')
                    ->label(__('loyalty.columns.direction'))
                    ->options(collect(LedgerDirection::cases())
                        ->mapWithKeys(fn (LedgerDirection $d) => [$d->value => $d->label()])
                        ->all()),
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
            ])
            ->headerActions([
                Tables\Actions\Action::make('adjustBalance')
                    ->label(__('loyalty.actions.adjust_balance'))
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('user_id')
                            ->label(__('loyalty.columns.user'))
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('vendor_profile_id')
                            ->label(__('loyalty.columns.vendor'))
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('points_delta')
                            ->label(__('loyalty.fields.points_delta'))
                            ->numeric()
                            ->required()
                            ->helperText(__('loyalty.help.points_delta')),
                        Forms\Components\TextInput::make('reason_en')
                            ->label(__('loyalty.fields.reason_en'))
                            ->required()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('reason_ar')
                            ->label(__('loyalty.fields.reason_ar'))
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (array $data): void {
                        try {
                            app(AdjustLoyaltyBalanceAction::class)->execute(
                                (int) $data['user_id'],
                                (int) $data['vendor_profile_id'],
                                (int) $data['points_delta'],
                                (string) $data['reason_en'],
                                (string) $data['reason_ar'],
                                (int) auth()->id(),
                            );

                            Notification::make()
                                ->title(__('loyalty.notifications.adjust_success'))
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title(__('loyalty.notifications.adjust_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyLedger::route('/'),
        ];
    }
}
