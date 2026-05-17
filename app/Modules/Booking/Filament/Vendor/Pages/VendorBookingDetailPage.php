<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Vendor\Pages;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class VendorBookingDetailPage extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'vendor-portal.pages.vendor-booking-detail';

    public string $bookingVendor;

    private ?BookingVendor $resolvedBookingVendor = null;

    public function mount(string $bookingVendor): void
    {
        $record = BookingVendor::query()
            ->where('public_id', $bookingVendor)
            ->with(['booking.customer', 'booking.address', 'items'])
            ->firstOrFail();

        abort_if($record->vendor_profile_id !== $this->getVendorProfile()->id, 403);

        $this->bookingVendor = $bookingVendor;
        $this->resolvedBookingVendor = $record;
    }

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.bookings.detail_title');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $record = $this->getRecord();

        return $infolist
            ->record($record)
            ->schema([
                Section::make(__('vendor-portal.bookings.booking_info'))
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('booking.reference_no')
                                ->label(__('vendor-portal.bookings.reference')),
                            TextEntry::make('booking.event_starts_at')
                                ->label(__('vendor-portal.bookings.event_date'))
                                ->dateTime('d M Y, H:i'),
                            TextEntry::make('booking.customer.name')
                                ->label(__('vendor-portal.bookings.customer')),
                        ]),
                        Grid::make(2)->schema([
                            TextEntry::make('sub_status')
                                ->label(__('vendor-portal.bookings.status'))
                                ->badge()
                                ->color(fn ($state) => match ($state?->value ?? $state) {
                                    'accepted', 'in_progress', 'completed' => 'success',
                                    'pending', 'modified' => 'warning',
                                    default => 'gray',
                                }),
                            TextEntry::make('booking.address.address_line')
                                ->label(__('vendor-portal.bookings.address'))
                                ->default('—'),
                        ]),
                    ]),

                Section::make(__('vendor-portal.bookings.items'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('name_snapshot')
                                        ->label(__('vendor-portal.services.name'))
                                        ->formatStateUsing(fn ($state) => is_array($state)
                                            ? ($state[app()->getLocale()] ?? $state['en'] ?? '—')
                                            : '—'),
                                    TextEntry::make('product_type')
                                        ->label(__('catalog.product_type'))
                                        ->badge()
                                        ->color(fn (ProductType $state) => match ($state) {
                                            ProductType::Rental => 'warning',
                                            ProductType::Sale => 'success',
                                            ProductType::Digital => 'info',
                                        }),
                                    TextEntry::make('quantity')
                                        ->label(__('vendor-portal.bookings.quantity')),
                                    TextEntry::make('line_total_minor')
                                        ->label(__('vendor-portal.bookings.line_total'))
                                        ->money('EGP', divideBy: 100),
                                ]),
                            ]),
                    ]),

                Section::make(__('vendor-portal.bookings.commission_breakdown'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label(__('vendor-portal.bookings.per_item_breakdown'))
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('name_snapshot')
                                        ->label(__('vendor-portal.services.name'))
                                        ->formatStateUsing(fn ($state) => is_array($state)
                                            ? ($state[app()->getLocale()] ?? $state['en'] ?? '—')
                                            : '—'),
                                    TextEntry::make('line_total_minor')
                                        ->label(__('vendor-portal.bookings.gross'))
                                        ->money('EGP', divideBy: 100),
                                    TextEntry::make('commission_bps')
                                        ->label(__('vendor-portal.bookings.commission_rate'))
                                        ->formatStateUsing(fn (int $state) => number_format($state / 100, 1).'%'),
                                    TextEntry::make('commission_minor')
                                        ->label(__('vendor-portal.bookings.commission_amount'))
                                        ->money('EGP', divideBy: 100)
                                        ->color('danger'),
                                ]),
                            ]),

                        Grid::make(3)->schema([
                            TextEntry::make('subtotal_minor')
                                ->label(__('vendor-portal.bookings.gross_total'))
                                ->money('EGP', divideBy: 100),
                            TextEntry::make('commission_minor')
                                ->label(__('vendor-portal.bookings.total_commission'))
                                ->money('EGP', divideBy: 100)
                                ->color('danger'),
                            TextEntry::make('vendor_payout_minor')
                                ->label(__('vendor-portal.bookings.net_payout'))
                                ->money('EGP', divideBy: 100)
                                ->color('success')
                                ->weight('bold'),
                        ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('decide')
                ->label(__('vendor-portal.decision.title'))
                ->icon('heroicon-o-scale')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->sub_status === VendorSubStatus::Pending)
                ->url(fn () => VendorBookingDecisionPage::getUrl(['bookingVendor' => $this->bookingVendor])),

            Action::make('viewPayments')
                ->label(__('vendor-portal.bookings.view_payments'))
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->url(fn () => VendorBookingPaymentsPage::getUrl(['bookingVendor' => $this->bookingVendor])),

            Action::make('back')
                ->label(__('vendor-portal.back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => VendorIncomingBookingsPage::getUrl()),
        ];
    }

    private function getRecord(): BookingVendor
    {
        if ($this->resolvedBookingVendor === null) {
            $this->resolvedBookingVendor = BookingVendor::query()
                ->where('public_id', $this->bookingVendor)
                ->with(['booking.customer', 'booking.address', 'items'])
                ->firstOrFail();
        }

        return $this->resolvedBookingVendor;
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
