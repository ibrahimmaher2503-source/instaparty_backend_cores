<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages;

use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\RevokeVendorTypeAction;
use App\Modules\Identity\Application\Actions\SuspendVendorAction;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use App\Modules\Shared\Domain\Enums\ProductType;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorProfile extends ViewRecord
{
    protected static string $resource = VendorProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveForRental')
                ->label('Approve for Rental')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (VendorProfile $record): bool => $this->canApproveType($record, ProductType::Rental))
                ->requiresConfirmation()
                ->action(fn (VendorProfile $record) => $this->approveType($record, ProductType::Rental)),

            Action::make('approveForSale')
                ->label('Approve for Sale')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (VendorProfile $record): bool => $this->canApproveType($record, ProductType::Sale))
                ->requiresConfirmation()
                ->action(fn (VendorProfile $record) => $this->approveType($record, ProductType::Sale)),

            Action::make('approveForDigital')
                ->label('Approve for Digital')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (VendorProfile $record): bool => $this->canApproveType($record, ProductType::Digital))
                ->requiresConfirmation()
                ->action(fn (VendorProfile $record) => $this->approveType($record, ProductType::Digital)),

            Action::make('revokeType')
                ->label('Revoke Type')
                ->icon('heroicon-o-minus-circle')
                ->color('warning')
                ->visible(fn (VendorProfile $record): bool => $record->approvedTypes()->whereNull('revoked_at')->exists())
                ->form([
                    Select::make('product_type')
                        ->options(['rental' => 'Rental', 'sale' => 'Sale', 'digital' => 'Digital'])
                        ->required(),
                    Textarea::make('revoke_reason_en')
                        ->label('Revoke Reason (EN)')
                        ->maxLength(1000),
                ])
                ->action(function (VendorProfile $record, array $data): void {
                    $reason = array_filter(['en' => $data['revoke_reason_en'] ?? null]);
                    app(RevokeVendorTypeAction::class)->execute($record, ProductType::from($data['product_type']), $reason);
                    Notification::make()->title('Type approval revoked')->warning()->send();
                }),

            Action::make('suspend')
                ->label('Suspend Vendor')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (VendorProfile $record): bool => $record->approval_status !== ApprovalStatus::Suspended)
                ->action(function (VendorProfile $record): void {
                    app(SuspendVendorAction::class)->execute($record);
                    Notification::make()->title('Vendor suspended')->danger()->send();
                }),
        ];
    }

    private function canApproveType(VendorProfile $record, ProductType $type): bool
    {
        if ($record->approval_status !== ApprovalStatus::Approved) {
            return false;
        }

        return ! $record->approvedTypes()
            ->where('product_type', $type->value)
            ->whereNull('revoked_at')
            ->exists();
    }

    private function approveType(VendorProfile $record, ProductType $type): void
    {
        app(ApproveVendorForTypeAction::class)->execute($record, $type);
        Notification::make()->title('Vendor approved for '.$type->value)->success()->send();
        $this->refreshFormData(['approval_status']);
    }
}
