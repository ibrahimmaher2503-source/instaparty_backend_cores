<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\WithdrawalResource\Pages;

use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource;
use App\Modules\Settlement\Filament\Resources\WithdrawalResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewWithdrawal extends ViewRecord
{
    protected static string $resource = WithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewLedger')
                ->label('View Wallet Ledger')
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->url(function (): ?string {
                    /** @var Withdrawal $withdrawal */
                    $withdrawal = $this->record;
                    $wallet = Wallet::query()
                        ->where('owner_type', 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile')
                        ->where('owner_id', $withdrawal->vendor_profile_id)
                        ->where('currency', $withdrawal->requested_amount_currency)
                        ->first();

                    if ($wallet === null) {
                        return null;
                    }

                    return WalletLedgerViewerResource::getUrl('view', ['record' => $wallet->id]);
                })
                ->visible(fn (): bool => $this->record->reserved_ledger_entry_id !== null),
        ];
    }
}
