<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Filament\Resources\ChatModerationFlagResource\Pages;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only Filament Resource for the chat moderation flag queue.
 *
 * FR-EXT-036-009 / FR-EXT-036-011 / SC-007.
 *  - No CreateAction, no EditAction, no DeleteAction.
 *  - Only `index` and `view` pages registered.
 *  - Permission gates: `chat_moderation.view` to list/view,
 *    `chat_moderation.resolve_flag` to resolve, `chat_moderation.escalate` to escalate.
 */
class ChatModerationFlagResource extends Resource
{
    protected static ?string $model = ChatModerationFlag::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $modelLabel = 'Moderation Flag';

    protected static ?string $pluralModelLabel = 'Moderation Flags';

    protected static ?int $navigationSort = 51;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('chat_moderation.nav.moderation_flags');
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
            'index' => Pages\ListChatModerationFlags::route('/'),
            'view' => Pages\ViewChatModerationFlag::route('/{record}'),
        ];
    }
}
