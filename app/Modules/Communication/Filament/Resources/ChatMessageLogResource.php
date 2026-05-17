<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Filament\Resources\ChatMessageLogResource\Pages;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only Filament Resource for the chat message log (MySQL audit mirror of Firestore).
 *
 * FR-EXT-036-015 / SC-007 (the inviolable invariant):
 *  - No CreateAction, no EditAction, no DeleteAction, no inline editable columns.
 *  - Only `index` and `view` pages registered.
 *  - The `Mark as Off-Platform Attempt` row action is the ONLY mutation path,
 *    and it touches only the allow-listed columns (`flagged`, `flag_reason`).
 */
class ChatMessageLogResource extends Resource
{
    protected static ?string $model = ChatMessageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $modelLabel = 'Chat Message Log';

    protected static ?string $pluralModelLabel = 'Chat Message Log';

    protected static ?int $navigationSort = 52;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('chat_moderation.nav.message_log');
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatMessageLogs::route('/'),
            'view' => Pages\ViewChatMessageLog::route('/{record}'),
        ];
    }
}
