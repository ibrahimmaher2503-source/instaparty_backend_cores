<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages;

use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\RejectVendorProfileAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ReviewVendorApplication extends ViewRecord
{
    protected static string $resource = VendorApprovalQueueResource::class;

    public function getTitle(): string
    {
        return 'Review Vendor Application';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve Profile')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve this vendor?')
                ->modalDescription('This grants the vendor the approved status. You can grant per-product-type permissions afterwards.')
                ->visible(fn () => auth()->user()?->can('approve_vendor_profile'))
                ->action(function (VendorProfile $record): void {
                    app(ApproveVendorProfileAction::class)->execute($record);
                    Notification::make()->title('Vendor profile approved')->success()->send();
                    $this->redirect(VendorApprovalQueueResource::getUrl('index'));
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => auth()->user()?->can('approve_vendor_profile'))
                ->form([
                    Textarea::make('rejection_reason_en')
                        ->label('Rejection Reason (EN)')
                        ->required()
                        ->maxLength(1000),
                    Textarea::make('rejection_reason_ar')
                        ->label('Rejection Reason (AR)')
                        ->maxLength(1000),
                ])
                ->action(function (VendorProfile $record, array $data): void {
                    $reason = array_filter([
                        'en' => $data['rejection_reason_en'] ?? null,
                        'ar' => $data['rejection_reason_ar'] ?? null,
                    ]);
                    app(RejectVendorProfileAction::class)->execute($record, $reason);
                    Notification::make()->title('Vendor profile rejected')->danger()->send();
                    $this->redirect(VendorApprovalQueueResource::getUrl('index'));
                }),
        ];
    }
}
