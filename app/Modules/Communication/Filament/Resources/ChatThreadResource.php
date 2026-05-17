<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Filament\Resources\ChatThreadResource\Pages;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Read-only Filament Resource for the Restricted Chat moderation surface.
 *
 * FR-EXT-036-001 / FR-EXT-036-004 / FR-EXT-036-005 / SC-007:
 *  - No CreateAction, no EditAction, no DeleteAction, no Replicate.
 *  - Only `index` and `view` pages registered.
 *  - Permission gate: `chat_moderation.view`.
 */
class ChatThreadResource extends Resource
{
    protected static ?string $model = ChatThread::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $modelLabel = 'Restricted Chat';

    protected static ?string $pluralModelLabel = 'Restricted Chats';

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('chat_moderation.nav.restricted_chat');
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('chat_moderation.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make(__('chat_moderation.sections.booking_context'))
                ->schema([
                    TextEntry::make('booking.public_id')
                        ->label(__('chat_moderation.columns.booking_ref'))
                        ->placeholder('—'),
                    TextEntry::make('customer.name')
                        ->label(__('chat_moderation.columns.customer'))
                        ->default('<deleted user>'),
                    TextEntry::make('vendorProfile.business_name')
                        ->label(__('chat_moderation.columns.vendor'))
                        ->getStateUsing(function (ChatThread $record): string {
                            $name = $record->vendorProfile?->business_name;
                            if (is_array($name)) {
                                $locale = app()->getLocale();

                                return $name[$locale] ?? ($name['en'] ?? '—');
                            }

                            return $name ?? '—';
                        }),
                    TextEntry::make('first_product_type')
                        ->label(__('chat_moderation.columns.product_type'))
                        ->getStateUsing(function (ChatThread $record): string {
                            $bookingId = $record->booking_id;
                            if (! $bookingId) {
                                return '—';
                            }
                            $row = DB::table('booking_items')
                                ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
                                ->where('booking_vendors.booking_id', $bookingId)
                                ->select('booking_items.product_type')
                                ->first();

                            return $row?->product_type ?? '—';
                        }),
                    TextEntry::make('status')
                        ->label(__('chat_moderation.columns.status'))
                        ->badge(),
                    TextEntry::make('frozen_at')
                        ->label(__('chat_moderation.columns.frozen_at'))
                        ->dateTime(timezone: config('app.timezone', 'UTC'))
                        ->placeholder('—'),
                    TextEntry::make('frozenByUser.name')
                        ->label(__('chat_moderation.columns.frozen_by'))
                        ->placeholder('—'),
                ])
                ->columns(2),

            Section::make(__('chat_moderation.sections.messages'))
                ->schema([
                    RepeatableEntry::make('messages')
                        ->label('')
                        ->schema([
                            TextEntry::make('sender.name')
                                ->label(__('chat_moderation.audit.event_actor'))
                                ->default('<deleted user>'),
                            TextEntry::make('created_at')
                                ->label(__('chat_moderation.columns.last_message_at'))
                                ->dateTime(timezone: config('app.timezone', 'UTC')),
                            TextEntry::make('body_view')
                                ->label(__('chat_moderation.sections.message_body'))
                                ->getStateUsing(function ($record): string {
                                    if ($record->redacted) {
                                        $en = '<message redacted by moderation>';
                                        $ar = '<تم حجب الرسالة بواسطة الإشراف>';

                                        return $en.' / '.$ar;
                                    }

                                    return '['.__('chat_moderation.firestore_placeholder').']';
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ])
                ->collapsible(),

            Section::make(__('chat_moderation.sections.audit_timeline'))
                ->schema([
                    RepeatableEntry::make('audit_timeline')
                        ->label('')
                        ->getStateUsing(function (ChatThread $record): array {
                            $flagIds = DB::table('chat_moderation_flags')
                                ->join('chat_message_log', 'chat_message_log.id', '=', 'chat_moderation_flags.chat_message_log_id')
                                ->where('chat_message_log.chat_thread_id', $record->id)
                                ->pluck('chat_moderation_flags.id')
                                ->all();

                            $threadAuditables = [
                                'App\\Modules\\Communication\\Domain\\Models\\ChatThread',
                                ChatThread::class,
                            ];
                            $flagAuditables = [
                                'App\\Modules\\Communication\\Domain\\Models\\ChatModerationFlag',
                                \App\Modules\Communication\Domain\Models\ChatModerationFlag::class,
                            ];

                            $rows = DB::table('audit_logs')
                                ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                                ->where(function ($q) use ($threadAuditables, $record, $flagAuditables, $flagIds) {
                                    $q->where(function ($q2) use ($threadAuditables, $record) {
                                        $q2->whereIn('audit_logs.auditable_type', $threadAuditables)
                                            ->where('audit_logs.auditable_id', $record->id);
                                    });
                                    if (! empty($flagIds)) {
                                        $q->orWhere(function ($q3) use ($flagAuditables, $flagIds) {
                                            $q3->whereIn('audit_logs.auditable_type', $flagAuditables)
                                                ->whereIn('audit_logs.auditable_id', $flagIds);
                                        });
                                    }
                                })
                                ->orderBy('audit_logs.created_at', 'asc')
                                ->select([
                                    'audit_logs.action',
                                    'audit_logs.changes',
                                    'audit_logs.created_at',
                                    'users.name as actor_name',
                                ])
                                ->get();

                            return $rows->map(function ($row): array {
                                $changes = is_string($row->changes) ? json_decode($row->changes, true) : (array) $row->changes;
                                $reasonEn = $changes['reason_en'] ?? $changes['note_en'] ?? $changes['summary_en'] ?? null;
                                $reasonAr = $changes['reason_ar'] ?? $changes['note_ar'] ?? $changes['summary_ar'] ?? null;
                                $reason = trim(($reasonEn ?? '').(($reasonEn && $reasonAr) ? ' / ' : '').($reasonAr ?? ''));

                                return [
                                    'action' => $row->action,
                                    'actor' => $row->actor_name ?? '—',
                                    'reason' => $reason !== '' ? $reason : '—',
                                    'created_at' => $row->created_at,
                                ];
                            })->all();
                        })
                        ->schema([
                            TextEntry::make('created_at')
                                ->label(__('chat_moderation.audit.event_at'))
                                ->dateTime(timezone: config('app.timezone', 'UTC')),
                            TextEntry::make('actor')
                                ->label(__('chat_moderation.audit.event_actor')),
                            TextEntry::make('action')
                                ->label(__('chat_moderation.audit.event_action')),
                            TextEntry::make('reason')
                                ->label(__('chat_moderation.audit.event_reason'))
                                ->columnSpanFull(),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatThreads::route('/'),
            'view' => Pages\ViewChatThread::route('/{record}'),
        ];
    }
}
