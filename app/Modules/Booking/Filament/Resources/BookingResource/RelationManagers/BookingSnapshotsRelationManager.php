<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers;

use App\Modules\Booking\Domain\Models\BookingSnapshot;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookingSnapshotsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'snapshots';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('booking.relations.snapshots');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['triggeredBy']))
            ->columns([
                Tables\Columns\TextColumn::make('version')
                    ->label(__('booking.columns.version'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('trigger_kind')
                    ->label(__('booking.columns.trigger_kind'))
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('triggered_by')
                    ->label(__('booking.columns.actor'))
                    ->getStateUsing(fn (BookingSnapshot $record): string => self::resolveActorName($record))
                    ->sortable(),
                Tables\Columns\TextColumn::make('snapshot')
                    ->label(__('booking.columns.snapshot'))
                    ->getStateUsing(fn (BookingSnapshot $record): string => self::prettySnapshot($record->snapshot))
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading(__('booking.relations.snapshots'))
            ->emptyStateDescription(__('booking.empty_states.snapshots'))
            ->defaultSort('created_at', 'desc');
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(Model $record): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }

    private static function resolveActorName(BookingSnapshot $record): string
    {
        $actor = $record->triggeredBy;

        if (! $actor) {
            return $record->triggered_by ? '#'.$record->triggered_by : 'System';
        }

        return $actor->name;
    }

    private static function prettySnapshot(array $snapshot): string
    {
        return json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
