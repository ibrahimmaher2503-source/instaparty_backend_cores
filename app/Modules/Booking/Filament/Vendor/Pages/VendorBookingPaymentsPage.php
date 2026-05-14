<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Vendor\Pages;

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorBookingPaymentsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'vendor-portal.pages.vendor-booking-payments';

    public string $bookingVendor;

    private ?BookingVendor $resolvedBookingVendor = null;

    public function mount(string $bookingVendor): void
    {
        $record = BookingVendor::query()
            ->where('public_id', $bookingVendor)
            ->with('booking')
            ->firstOrFail();

        abort_if($record->vendor_profile_id !== $this->getVendorProfile()->id, 403);

        $this->bookingVendor = $bookingVendor;
        $this->resolvedBookingVendor = $record;
    }

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.bookings.payment_history_title');
    }

    public function table(Table $table): Table
    {
        $bookingId = $this->getBookingVendorRecord()->booking_id;

        return $table
            ->query(
                Payment::query()
                    ->where('booking_id', $bookingId)
                    ->with('booking.customer')
                    ->latest()
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('vendor-portal.bookings.payment_date'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('booking.customer.name')
                    ->label(__('vendor-portal.bookings.customer'))
                    ->default('—'),

                TextColumn::make('amount_minor')
                    ->label(__('vendor-portal.bookings.amount'))
                    ->money('EGP', divideBy: 100)
                    ->formatStateUsing(function (Payment $record): string {
                        $amount = number_format($record->amount_minor / 100, 2);
                        $formatted = "EGP {$amount}";
                        if ($record->status === PaymentStatus::Refunded
                            || $record->status === PaymentStatus::PartiallyRefunded
                        ) {
                            return "-{$formatted}";
                        }

                        return $formatted;
                    })
                    ->color(fn (Payment $record) => match ($record->status) {
                        PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded => 'danger',
                        PaymentStatus::Captured => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label(__('vendor-portal.bookings.payment_status'))
                    ->badge()
                    ->color(fn (PaymentStatus $state) => match ($state) {
                        PaymentStatus::Captured => 'success',
                        PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded => 'danger',
                        PaymentStatus::Pending, PaymentStatus::Authorized => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('gateway_ref')
                    ->label(__('vendor-portal.bookings.gateway_ref'))
                    ->copyable()
                    ->limit(30),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('vendor-portal.back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => VendorBookingDetailPage::getUrl(['bookingVendor' => $this->bookingVendor])),
        ];
    }

    private function getBookingVendorRecord(): BookingVendor
    {
        if ($this->resolvedBookingVendor === null) {
            $this->resolvedBookingVendor = BookingVendor::query()
                ->where('public_id', $this->bookingVendor)
                ->with('booking')
                ->firstOrFail();
        }

        return $this->resolvedBookingVendor;
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
