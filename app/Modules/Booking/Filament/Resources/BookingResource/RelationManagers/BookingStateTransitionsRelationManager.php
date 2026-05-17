<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers;

use App\Modules\Shared\Domain\Models\StateTransition;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BookingStateTransitionsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'stateTransitions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('booking.relations.state_transitions');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['triggeredByUser']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('from_state')
                    ->label(__('booking.columns.from_state'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? Str::headline(str_replace('_', ' ', $state)) : '—'),
                Tables\Columns\TextColumn::make('to_state')
                    ->label(__('booking.columns.to_state'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('trigger_kind')
                    ->label(__('booking.columns.trigger_kind'))
                    ->badge(),
                Tables\Columns\TextColumn::make('triggered_by')
                    ->label(__('booking.columns.actor'))
                    ->getStateUsing(fn (StateTransition $record): string => self::resolveActorName($record)),
                Tables\Columns\TextColumn::make('context')
                    ->label(__('booking.columns.context'))
                    ->getStateUsing(fn (StateTransition $record): string => self::prettyContext($record->context))
                    ->wrap(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading(__('booking.relations.state_transitions'))
            ->emptyStateDescription(__('booking.empty_states.state_transitions'))
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

    private static function resolveActorName(StateTransition $record): string
    {
        $actor = $record->triggeredByUser;

        if (! $actor) {
            return $record->triggered_by ? '#'.$record->triggered_by : 'System';
        }

        return $actor->name;
    }

    private static function prettyContext(?array $context): string
    {
        if ($context === null) {
            return '{}';
        }

        return json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
