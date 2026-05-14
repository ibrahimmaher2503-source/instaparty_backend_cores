<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Identity\Application\Actions\ChangeVendorPasswordAction;
use App\Modules\Identity\Application\Actions\UpdateVendorEmailAction;
use App\Modules\Identity\Application\Actions\UpdateVendorPhoneAction;
use App\Modules\Identity\Domain\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rules\Password;

class VendorAccountPage extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'settings';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'vendor-portal.pages.vendor-account';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.account.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.account.title');
    }

    public function accountInfolist(Infolist $infolist): Infolist
    {
        /** @var User $user */
        $user = auth()->user();

        return $infolist
            ->record($user)
            ->schema([
                Section::make(__('vendor-portal.account.title'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('email')
                            ->label(__('vendor-portal.account.update_email'))
                            ->copyable(),
                        TextEntry::make('phone_e164')
                            ->label(__('vendor-portal.account.update_phone'))
                            ->copyable(),
                        TextEntry::make('created_at')
                            ->label(__('vendor-portal.account.member_since'))
                            ->dateTime('d M Y'),
                        TextEntry::make('last_login_at')
                            ->label(__('vendor-portal.account.last_login'))
                            ->dateTime('d M Y, H:i')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changePassword')
                ->label(__('vendor-portal.account.change_password'))
                ->icon('heroicon-o-lock-closed')
                ->form([
                    TextInput::make('current_password')
                        ->label(__('vendor-portal.account.current_password'))
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('new_password')
                        ->label(__('vendor-portal.account.new_password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(Password::default())
                        ->same('confirm_password'),
                    TextInput::make('confirm_password')
                        ->label(__('vendor-portal.account.confirm_password'))
                        ->password()
                        ->revealable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    app(ChangeVendorPasswordAction::class)->execute(
                        $user,
                        $data['current_password'],
                        $data['new_password'],
                    );
                    Notification::make()->title(__('vendor-portal.account.password_changed'))->success()->send();
                }),

            Action::make('updateEmail')
                ->label(__('vendor-portal.account.update_email'))
                ->icon('heroicon-o-envelope')
                ->form([
                    TextInput::make('new_email')
                        ->label(__('vendor-portal.account.new_email'))
                        ->email()
                        ->required()
                        ->unique('users', 'email', ignorable: auth()->user()),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    app(UpdateVendorEmailAction::class)->execute($user, $data['new_email']);
                    Notification::make()->title(__('vendor-portal.account.email_changed'))->success()->send();
                }),

            Action::make('updatePhone')
                ->label(__('vendor-portal.account.update_phone'))
                ->icon('heroicon-o-phone')
                ->form([
                    TextInput::make('new_phone')
                        ->label(__('vendor-portal.account.new_phone'))
                        ->placeholder('+201234567890')
                        ->required()
                        ->maxLength(20)
                        ->helperText('Include country code, e.g. +201234567890'),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    app(UpdateVendorPhoneAction::class)->execute($user, $data['new_phone']);
                    Notification::make()->title(__('vendor-portal.account.phone_changed'))->success()->send();
                }),
        ];
    }
}
