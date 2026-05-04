<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Application\Actions\AcknowledgeInboxItemAction;
use App\Modules\Communication\Application\Actions\BatchResolveInboxItemsAction;
use App\Modules\Communication\Application\Actions\ReassignInboxItemAction;
use App\Modules\Communication\Application\Actions\ResolveInboxItemAction;
use App\Modules\Communication\Application\Actions\SnoozeInboxItemAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Filament\Resources\AdminInboxResource\Pages;
use App\Modules\Identity\Domain\Models\User;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AdminInboxResource extends Resource
{
    protected static ?string $model = AdminInboxItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationLabel = 'My Inbox';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.inbox');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', AdminInboxStatus::Unread->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('admin_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (AdminInboxSeverity $state): string => $state->color())
                    ->formatStateUsing(fn (AdminInboxSeverity $state) => $state->label())
                    ->label(__('communication.inbox.severity')),

                TextColumn::make('title')
                    ->getStateUsing(fn (AdminInboxItem $record): string => $record->getTranslation('title', app()->getLocale()))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')) LIKE ?", ["%{$search}%"]))
                    ->limit(60)
                    ->label(__('communication.inbox.title')),

                TextColumn::make('source')
                    ->getStateUsing(fn (AdminInboxItem $record): string => "{$record->source_type} #{$record->source_id}")
                    ->label(__('communication.inbox.source')),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (AdminInboxStatus $state): string => $state->color())
                    ->formatStateUsing(fn (AdminInboxStatus $state) => $state->label())
                    ->label(__('communication.inbox.status')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('communication.inbox.received_at')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(AdminInboxStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                    ->label(__('communication.inbox.status')),

                SelectFilter::make('severity')
                    ->options(collect(AdminInboxSeverity::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                    ->label(__('communication.inbox.severity')),
            ])
            ->actions([
                Action::make('read')
                    ->label(__('communication.inbox.actions.mark_read'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (AdminInboxItem $record): bool => $record->status === AdminInboxStatus::Unread)
                    ->action(function (AdminInboxItem $record) {
                        app(AcknowledgeInboxItemAction::class)->execute($record, auth()->user());
                        Notification::make()->title(__('communication.inbox.marked_read'))->success()->send();
                    }),

                Action::make('snooze')
                    ->label(__('communication.inbox.actions.snooze'))
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->form([
                        Select::make('hours')
                            ->label(__('communication.inbox.snooze_duration'))
                            ->options([1 => '1 hour', 4 => '4 hours', 24 => '24 hours'])
                            ->required(),
                    ])
                    ->action(function (AdminInboxItem $record, array $data) {
                        app(SnoozeInboxItemAction::class)->execute($record, auth()->user(), (int) $data['hours']);
                        Notification::make()->title(__('communication.inbox.snoozed'))->success()->send();
                    })
                    ->visible(fn (AdminInboxItem $record): bool => in_array($record->status, [AdminInboxStatus::Unread, AdminInboxStatus::Read], strict: true)),

                Action::make('reassign')
                    ->label(__('communication.inbox.actions.reassign'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->form([
                        Select::make('target_admin_id')
                            ->label(__('communication.inbox.reassign_to'))
                            ->options(fn () => User::role(['super_admin', 'admin', 'vendor_manager', 'ops_manager'])->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (AdminInboxItem $record, array $data) {
                        $target = User::findOrFail($data['target_admin_id']);
                        app(ReassignInboxItemAction::class)->execute($record, auth()->user(), $target);
                        Notification::make()->title(__('communication.inbox.reassigned'))->success()->send();
                    }),

                Action::make('resolve')
                    ->label(__('communication.inbox.actions.resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (AdminInboxItem $record) {
                        app(ResolveInboxItemAction::class)->execute($record, auth()->user());
                        Notification::make()->title(__('communication.inbox.resolved'))->success()->send();
                    })
                    ->visible(fn (AdminInboxItem $record): bool => $record->status !== AdminInboxStatus::Resolved),
            ])
            ->bulkActions([
                BulkAction::make('batch_resolve')
                    ->label(__('communication.inbox.actions.batch_resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $ids = $records->pluck('id')->all();
                        $count = app(BatchResolveInboxItemsAction::class)->execute($ids, auth()->user());
                        Notification::make()->title(__('communication.inbox.batch_resolved', ['count' => $count]))->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminInboxItems::route('/'),
        ];
    }
}
