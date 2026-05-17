<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Vendor\Pages;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorWalletPage extends Page implements HasInfolists, HasTable
{
    use InteractsWithInfolists;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'finance';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'vendor-portal.pages.vendor-wallet';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.wallet.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.wallet.title');
    }

    public function walletInfolist(Infolist $infolist): Infolist
    {
        $wallet = $this->getWallet();

        return $infolist
            ->record($wallet)
            ->schema([
                TextEntry::make('balance_minor')
                    ->label(__('vendor-portal.wallet.current_balance'))
                    ->state(fn () => $wallet?->balance_minor ?? 0)
                    ->money('EGP', divideBy: 100),
                TextEntry::make('available_balance')
                    ->label(__('vendor-portal.wallet.available_balance'))
                    ->state(fn () => max(0, ($wallet?->balance_minor ?? 0) - ($wallet?->pending_withdrawal_minor ?? 0)))
                    ->money('EGP', divideBy: 100),
                TextEntry::make('pending_withdrawal_minor')
                    ->label(__('vendor-portal.wallet.pending_balance'))
                    ->state(fn () => $wallet?->pending_withdrawal_minor ?? 0)
                    ->money('EGP', divideBy: 100),
            ]);
    }

    public function table(Table $table): Table
    {
        $vendor = $this->getVendorProfile();

        return $table
            ->query(
                Withdrawal::query()
                    ->where('vendor_profile_id', $vendor->id)
                    ->orderByDesc('requested_at')
            )
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('vendor-portal.withdrawals.reference'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('vendor-portal.withdrawals.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'  => 'warning',
                        'approved' => 'info',
                        'paid'     => 'success',
                        'rejected' => 'danger',
                        default    => 'gray',
                    }),
                TextColumn::make('requested_amount_minor')
                    ->label(__('vendor-portal.withdrawals.amount'))
                    ->money('EGP', divideBy: 100),
                // Status timeline stacked column
                Stack::make([
                    TextColumn::make('requested_at')
                        ->label(__('vendor-portal.withdrawals.requested_at'))
                        ->dateTime('d M Y H:i')
                        ->icon('heroicon-m-arrow-up-circle')
                        ->placeholder('—'),
                    TextColumn::make('approved_at')
                        ->label(__('vendor-portal.withdrawals.approved_at'))
                        ->dateTime('d M Y H:i')
                        ->icon('heroicon-m-check-badge')
                        ->placeholder('—'),
                    TextColumn::make('paid_at')
                        ->label(__('vendor-portal.withdrawals.paid_at'))
                        ->dateTime('d M Y H:i')
                        ->icon('heroicon-m-banknotes')
                        ->placeholder('—'),
                ])->label(__('vendor-portal.withdrawals.timeline')),
                TextColumn::make('bank_transfer_reference')
                    ->label(__('vendor-portal.withdrawals.bank_reference'))
                    ->placeholder('—')
                    ->visible(fn (?Withdrawal $record): bool =>
                        $record !== null && $record->getRawOriginal('status') === 'paid'
                    ),
            ])
            ->actions([
                TableAction::make('downloadProof')
                    ->label(__('vendor-portal.withdrawals.download_proof'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(function (Withdrawal $record): ?string {
                        $media = $record->getFirstMedia('bank_proof');

                        if ($media === null) {
                            return null;
                        }

                        try {
                            return $media->getTemporaryUrl(now()->addMinutes(15));
                        } catch (\Exception) {
                            return null;
                        }
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (Withdrawal $record): bool =>
                        $record->getRawOriginal('status') === 'paid'
                        && $record->getFirstMedia('bank_proof') !== null
                    ),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestWithdrawal')
                ->label(__('vendor-portal.withdrawals.request'))
                ->icon('heroicon-o-arrow-up-right')
                ->color('primary')
                ->form([
                    TextInput::make('amount')
                        ->label(__('vendor-portal.withdrawals.amount').' (EGP)')
                        ->numeric()
                        ->required()
                        ->minValue(100)
                        ->hint(__('vendor-portal.withdrawals.bank_details')),
                ])
                ->action(function (array $data): void {
                    $vendor = $this->getVendorProfile();
                    $dto = new RequestWithdrawalDto(
                        vendorProfileId: $vendor->id,
                        requestedByUserId: auth()->id(),
                        amountMinor: (int) round((float) $data['amount'] * 100),
                        currency: 'EGP',
                        bankAccount: BankAccountSnapshot::fromArray([
                            'account_holder' => $vendor->bank_account_holder_name ?? '',
                            'iban' => $vendor->bank_iban ?? '',
                            'bank_name' => $vendor->bank_name ?? '',
                            'swift_bic' => $vendor->bank_swift_bic ?? '',
                        ]),
                    );
                    app(RequestWithdrawalAction::class)->execute($dto);
                    Notification::make()->title(__('vendor-portal.withdrawals.requested'))->success()->send();
                }),
        ];
    }

    private function getWallet(): ?Wallet
    {
        $vendor = $this->getVendorProfile();

        return Wallet::query()
            ->where('owner_type', VendorProfile::class)
            ->where('owner_id', $vendor->id)
            ->where('currency', 'EGP')
            ->first();
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
