<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Vendor\Pages;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
        $wallet = $this->getWallet();

        return $table
            ->query(
                WalletLedgerEntry::query()
                    ->where('wallet_id', $wallet?->id ?? 0)
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('catalog.created_at'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('entry_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (LedgerEntryType $state) => match ($state) {
                        LedgerEntryType::CommissionCredit, LedgerEntryType::ManualAdjustment => 'success',
                        LedgerEntryType::RefundDebit, LedgerEntryType::WithdrawalDebit => 'danger',
                    })
                    ->formatStateUsing(fn (LedgerEntryType $state) => match ($state) {
                        LedgerEntryType::CommissionCredit => __('vendor-portal.wallet.direction_credit'),
                        LedgerEntryType::RefundDebit, LedgerEntryType::WithdrawalDebit => __('vendor-portal.wallet.direction_debit'),
                        LedgerEntryType::ManualAdjustment => 'Adjustment',
                    }),
                TextColumn::make('amount_minor')
                    ->label(__('booking.total'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('description_key')
                    ->label(__('vendor-portal.wallet.description'))
                    ->limit(40),
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
