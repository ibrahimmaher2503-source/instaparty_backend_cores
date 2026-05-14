<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Application\Actions\GetVendorApprovalStatusAction;
use App\Modules\Identity\Application\Actions\RequestApprovalForTypeAction;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class VendorApprovalStatusPage extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'profile';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'vendor-portal.pages.vendor-approval-status';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.approval.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.approval.title');
    }

    public function approvalInfolist(Infolist $infolist): Infolist
    {
        $profile = $this->getVendorProfile();
        $status = app(GetVendorApprovalStatusAction::class)->execute($profile);

        $typeEntries = [];
        foreach (ProductType::cases() as $type) {
            $typeStatus = $status['types'][$type->value] ?? ['approved' => false, 'approved_at' => null];
            $typeEntries[] = TextEntry::make('type_'.$type->value)
                ->label(ucfirst($type->value))
                ->badge()
                ->state($typeStatus['approved'] ? __('vendor-portal.approval.approved') : __('vendor-portal.approval.pending'))
                ->color($typeStatus['approved'] ? 'success' : 'warning');
        }

        return $infolist
            ->record($profile)
            ->schema([
                Section::make(__('vendor-portal.approval.overall'))
                    ->schema([
                        TextEntry::make('approval_status')
                            ->label(__('vendor-portal.approval.overall'))
                            ->badge()
                            ->formatStateUsing(fn (ApprovalStatus $state) => match ($state) {
                                ApprovalStatus::Pending => __('vendor-portal.approval.pending'),
                                ApprovalStatus::Approved => __('vendor-portal.approval.approved'),
                                ApprovalStatus::Rejected => __('vendor-portal.approval.rejected'),
                                ApprovalStatus::Suspended => __('vendor-portal.approval.suspended'),
                                ApprovalStatus::ChangesRequested => 'Changes Requested',
                            })
                            ->color(fn (ApprovalStatus $state) => match ($state) {
                                ApprovalStatus::Pending => 'warning',
                                ApprovalStatus::Approved => 'success',
                                ApprovalStatus::Rejected => 'danger',
                                ApprovalStatus::Suspended => 'danger',
                                ApprovalStatus::ChangesRequested => 'warning',
                            }),
                        TextEntry::make('rejection_reason')
                            ->label(__('vendor-portal.approval.rejection_reason'))
                            ->formatStateUsing(fn ($record) => $record->getTranslation('rejection_reason', app()->getLocale()) ?? '—')
                            ->visible(fn (VendorProfile $record) => $record->approval_status === ApprovalStatus::Rejected),
                    ]),

                Section::make(__('vendor-portal.approval.per_type'))
                    ->schema($typeEntries)
                    ->columns(3),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestApproval')
                ->label(__('vendor-portal.approval.request_approval', ['type' => '']))
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    Select::make('product_type')
                        ->label('Product Type')
                        ->options([
                            ProductType::Rental->value => 'Rental',
                            ProductType::Sale->value => 'Sale',
                            ProductType::Digital->value => 'Digital',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $profile = $this->getVendorProfile();
                    app(RequestApprovalForTypeAction::class)->execute(
                        $profile,
                        ProductType::from($data['product_type']),
                    );
                    Notification::make()
                        ->title('Approval request submitted. An admin will review shortly.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
