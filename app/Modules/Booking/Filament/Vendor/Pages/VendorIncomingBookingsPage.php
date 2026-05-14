<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Vendor\Pages;

use App\Modules\Booking\Application\Actions\VendorAcceptBookingAction;
use App\Modules\Booking\Application\Actions\VendorRejectBookingAction;
use App\Modules\Booking\Application\DTOs\VendorAcceptDTO;
use App\Modules\Booking\Application\DTOs\VendorRejectDTO;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorIncomingBookingsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'vendor-portal.pages.vendor-incoming-bookings';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.bookings.incoming_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.bookings.incoming_title');
    }

    public function table(Table $table): Table
    {
        $vendorProfile = $this->getVendorProfile();

        return $table
            ->query(
                BookingVendor::query()
                    ->where('vendor_profile_id', $vendorProfile->id)
                    ->where('sub_status', VendorSubStatus::Pending)
                    ->with(['booking', 'booking.address', 'items'])
                    ->orderByRaw('ISNULL(response_deadline), response_deadline ASC')
            )
            ->columns([
                TextColumn::make('booking.reference_no')
                    ->label(__('vendor-portal.bookings.reference'))
                    ->searchable(),
                TextColumn::make('booking.customer.name')
                    ->label(__('vendor-portal.bookings.customer')),
                TextColumn::make('booking.event_starts_at')
                    ->label(__('vendor-portal.bookings.event_date'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label(__('vendor-portal.bookings.items_count'))
                    ->getStateUsing(fn (BookingVendor $record) => $record->items->count()),
                TextColumn::make('subtotal_minor')
                    ->label(__('vendor-portal.bookings.total'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('response_deadline')
                    ->label(__('vendor-portal.bookings.response_deadline'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->color(fn (BookingVendor $record) => $record->response_deadline?->isPast() ? 'danger' : 'warning'),
            ])
            ->actions([
                TableAction::make('view_details')
                    ->label(__('vendor-portal.bookings.view_details'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (BookingVendor $record) => VendorBookingDetailPage::getUrl(['bookingVendor' => $record->public_id])),

                TableAction::make('accept')
                    ->label(__('vendor-portal.bookings.accept'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('vendor-portal.bookings.accept_confirm'))
                    ->action(function (BookingVendor $record): void {
                        $profile = $this->getVendorProfile();
                        app(VendorAcceptBookingAction::class)->execute(new VendorAcceptDTO(
                            bookingVendorId: $record->id,
                            vendorProfileId: $profile->id,
                            proposedByUserId: (int) auth()->id(),
                        ));
                        Notification::make()->title(__('vendor-portal.bookings.accepted'))->success()->send();
                    }),

                TableAction::make('reject')
                    ->label(__('vendor-portal.bookings.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason_en')
                            ->label(__('vendor-portal.bookings.reject_reason').' (EN)')
                            ->rows(3),
                        Textarea::make('reason_ar')
                            ->label(__('vendor-portal.bookings.reject_reason').' (AR)')
                            ->rows(3),
                    ])
                    ->action(function (BookingVendor $record, array $data): void {
                        $reason = null;
                        if (! empty($data['reason_en']) || ! empty($data['reason_ar'])) {
                            $reason = ['en' => $data['reason_en'] ?? '', 'ar' => $data['reason_ar'] ?? ''];
                        }
                        $profile = $this->getVendorProfile();
                        app(VendorRejectBookingAction::class)->execute(new VendorRejectDTO(
                            bookingVendorId: $record->id,
                            vendorProfileId: $profile->id,
                            proposedByUserId: (int) auth()->id(),
                            rejectionReason: $reason,
                        ));
                        Notification::make()->title(__('vendor-portal.bookings.rejected'))->success()->send();
                    }),
            ]);
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
