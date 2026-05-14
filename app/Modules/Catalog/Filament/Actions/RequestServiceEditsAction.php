<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Actions;

use App\Modules\Catalog\Application\Actions\RequestDigitalServiceChangesAction;
use App\Modules\Catalog\Application\Actions\RequestRentalServiceChangesAction;
use App\Modules\Catalog\Application\Actions\RequestSaleServiceChangesAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Identity\Domain\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Str;

class RequestServiceEditsAction
{
    public static function make(): Action
    {
        return Action::make('requestChanges')
            ->label(__('catalog.request_changes'))
            ->icon('heroicon-o-exclamation-circle')
            ->color('warning')
            ->form([
                Repeater::make('items')
                    ->label(__('catalog.change_items'))
                    ->minItems(1)
                    ->schema([
                        TextInput::make('field_path')
                            ->label(__('catalog.field_path'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('requested_change_en')
                            ->label(__('catalog.requested_change_en'))
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                        Textarea::make('requested_change_ar')
                            ->label(__('catalog.requested_change_ar'))
                            ->required()
                            ->maxLength(1000)
                            ->rows(3)
                            ->extraInputAttributes(['dir' => 'rtl']),
                    ])
                    ->columnSpanFull(),
            ])
            ->requiresConfirmation()
            ->action(function (Service $record, array $data): void {
                $action = match ($record->product_type) {
                    ProductType::Rental => app(RequestRentalServiceChangesAction::class),
                    ProductType::Sale => app(RequestSaleServiceChangesAction::class),
                    ProductType::Digital => app(RequestDigitalServiceChangesAction::class),
                };

                $action->execute(
                    $record,
                    $data['items'] ?? [],
                    self::admin(),
                    request()->header('Idempotency-Key') ?? (string) Str::ulid(),
                );

                Notification::make()
                    ->title(__('catalog.changes_requested'))
                    ->body(__('catalog.changes_requested_message'))
                    ->success()
                    ->send();
            })
            ->visible(function (Service $record): bool {
                $user = auth()->user();

                return $user instanceof User
                    && app(ServicePolicy::class)->requestChanges($user, $record);
            });
    }

    private static function admin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
