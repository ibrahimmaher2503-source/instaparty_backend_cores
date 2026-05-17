<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\ChatThreadResource\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Filament\Resources\ChatThreadResource;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ListChatThreads extends ListRecords
{
    protected static string $resource = ChatThreadResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ChatThread::query()
                    ->with(['customer', 'vendorProfile.user', 'booking', 'frozenByUser'])
                    ->withCount(['unresolvedFlags'])
                    ->withMax('messages as last_message_at', 'created_at')
            )
            ->modifyQueryUsing(function (Builder $query): Builder {
                // MySQL has no NULLS LAST — emulate via a derived flag column.
                return $query
                    ->orderByRaw('CASE WHEN frozen_at IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderBy('frozen_at', 'desc')
                    ->orderByRaw('(SELECT MAX(created_at) FROM chat_message_log WHERE chat_message_log.chat_thread_id = chat_threads.id) IS NULL ASC')
                    ->orderByRaw('(SELECT MAX(created_at) FROM chat_message_log WHERE chat_message_log.chat_thread_id = chat_threads.id) DESC');
            })
            ->columns([
                TextColumn::make('booking.public_id')
                    ->label(__('chat_moderation.columns.booking_ref'))
                    ->placeholder('—')
                    ->limit(20)
                    ->searchable(),

                TextColumn::make('customer.name')
                    ->label(__('chat_moderation.columns.customer'))
                    ->default('<deleted user>')
                    ->searchable(),

                TextColumn::make('vendor_display')
                    ->label(__('chat_moderation.columns.vendor'))
                    ->getStateUsing(function (ChatThread $record): string {
                        $name = $record->vendorProfile?->business_name;
                        if (is_array($name)) {
                            $locale = app()->getLocale();

                            return $name[$locale] ?? ($name['en'] ?? '—');
                        }
                        if (is_string($name) && $name !== '') {
                            return $name;
                        }

                        return $record->vendorProfile?->user?->name ?? '—';
                    }),

                TextColumn::make('status')
                    ->label(__('chat_moderation.columns.status'))
                    ->badge()
                    ->formatStateUsing(function (ChatThread $record, $state): string {
                        if ($record->frozen_at !== null) {
                            return __('chat_moderation.status.frozen');
                        }

                        return __('chat_moderation.status.'.$state);
                    })
                    ->color(fn (ChatThread $record): string => $record->frozen_at !== null
                        ? 'danger'
                        : match ($record->status) {
                            'open' => 'success',
                            'locked' => 'warning',
                            'closed' => 'gray',
                            default => 'gray',
                        }),

                TextColumn::make('unresolved_flags_count')
                    ->label(__('chat_moderation.columns.flag_count'))
                    ->badge()
                    ->color(fn ($state): string => ((int) $state) > 0 ? 'warning' : 'gray'),

                TextColumn::make('last_message_at')
                    ->label(__('chat_moderation.columns.last_message_at'))
                    ->dateTime(timezone: config('app.timezone', 'UTC'))
                    ->placeholder('—'),

                TextColumn::make('frozenByUser.name')
                    ->label(__('chat_moderation.columns.frozen_by'))
                    ->placeholder('—'),

                TextColumn::make('frozen_at')
                    ->label(__('chat_moderation.columns.frozen_at'))
                    ->dateTime(timezone: config('app.timezone', 'UTC'))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => __('chat_moderation.status.open'),
                        'locked' => __('chat_moderation.status.locked'),
                        'closed' => __('chat_moderation.status.closed'),
                    ])
                    ->label(__('chat_moderation.columns.status')),

                TernaryFilter::make('has_open_flags')
                    ->label(__('chat_moderation.filters.has_open_flags'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('unresolvedFlags'),
                        false: fn (Builder $q) => $q->whereDoesntHave('unresolvedFlags'),
                        blank: fn (Builder $q) => $q,
                    ),

                SelectFilter::make('product_type')
                    ->label(__('chat_moderation.filters.product_type'))
                    ->options(collect(ProductType::cases())->mapWithKeys(fn (ProductType $t) => [$t->value => ucfirst($t->value)]))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $query->whereHas('booking.items', function (Builder $i) use ($value): void {
                            $i->where('product_type', $value);
                        });
                    }),

                Filter::make('last_message_range')
                    ->label(__('chat_moderation.columns.last_message_at'))
                    ->form([
                        DatePicker::make('from')->label(__('chat_moderation.filters.last_message_from')),
                        DatePicker::make('to')->label(__('chat_moderation.filters.last_message_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, $date) => $q->whereExists(function ($sub) use ($date): void {
                                    $sub->select(DB::raw(1))
                                        ->from('chat_message_log')
                                        ->whereColumn('chat_message_log.chat_thread_id', 'chat_threads.id')
                                        ->where('chat_message_log.created_at', '>=', Carbon::parse($date)->startOfDay());
                                })
                            )
                            ->when(
                                $data['to'] ?? null,
                                fn (Builder $q, $date) => $q->whereExists(function ($sub) use ($date): void {
                                    $sub->select(DB::raw(1))
                                        ->from('chat_message_log')
                                        ->whereColumn('chat_message_log.chat_thread_id', 'chat_threads.id')
                                        ->where('chat_message_log.created_at', '<=', Carbon::parse($date)->endOfDay());
                                })
                            );
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }
}
