<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Vendor\Pages;

use App\Modules\Booking\Application\Actions\VendorAcceptBookingAction;
use App\Modules\Booking\Application\Actions\VendorRejectBookingAction;
use App\Modules\Booking\Application\DTOs\VendorAcceptDTO;
use App\Modules\Booking\Application\DTOs\VendorRejectDTO;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingLock;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class VendorBookingDecisionPage extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'vendor-portal.pages.vendor-booking-decision';

    public string $bookingVendor;

    private ?BookingVendor $resolvedBookingVendor = null;

    private bool $isAddressInCoverage = false;

    /** @var array<int, string> service names with active inventory conflicts */
    private array $inventoryConflicts = [];

    private bool $isLocked = false;

    private bool $isReadOnly = false;

    private string $readOnlyReason = '';

    public function mount(string $bookingVendor): void
    {
        abort_if(auth()->user()->vendorProfile === null, 403);

        $record = BookingVendor::query()
            ->where('public_id', $bookingVendor)
            ->with([
                'booking.customer',
                'booking.address',
                'booking.customerNotes',
                'modifications' => fn ($q) => $q->latest(),
                'items.service',
            ])
            ->firstOrFail();

        abort_if($record->vendor_profile_id !== $this->getVendorProfile()->id, 403);

        $this->bookingVendor = $bookingVendor;
        $this->resolvedBookingVendor = $record;

        $this->resolveCoverage();
        $this->resolveInventory();
        $this->resolveLocks();
        $this->resolveReadOnlyState();
    }

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.decision.title');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->getRecord())
            ->schema([
                // Read-only banner — general warning (not deadline)
                Section::make(__('vendor-portal.decision.readonly.'.$this->readOnlyReason))
                    ->description(__('vendor-portal.decision.readonly.'.$this->readOnlyReason))
                    ->visible(fn () => $this->isReadOnly && $this->readOnlyReason !== '' && $this->readOnlyReason !== 'deadline_expired')
                    ->schema([])
                    ->extraAttributes(['class' => 'fi-color-warning']),

                // Read-only banner — deadline expired (danger)
                Section::make(__('vendor-portal.decision.deadline.expired'))
                    ->description(__('vendor-portal.decision.errors.deadline_expired'))
                    ->visible(fn () => $this->readOnlyReason === 'deadline_expired')
                    ->schema([])
                    ->extraAttributes(['class' => 'fi-color-danger']),

                // Booking information
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
                                ->formatStateUsing(fn ($state) => is_array($state)
                                    ? ($state[app()->getLocale()] ?? $state['en'] ?? '—')
                                    : ($state ?? '—')),
                        ]),
                    ]),

                // Coverage
                Section::make(__('vendor-portal.decision.coverage.heading'))
                    ->schema([
                        TextEntry::make('coverage_status')
                            ->label(__('vendor-portal.decision.coverage.label'))
                            ->getStateUsing(function () {
                                if ($this->getRecord()->booking?->address === null) {
                                    return __('vendor-portal.decision.coverage.unknown');
                                }

                                return $this->isAddressInCoverage
                                    ? __('vendor-portal.decision.coverage.inside')
                                    : __('vendor-portal.decision.coverage.outside');
                            }),
                    ]),

                // Deadline
                Section::make(__('vendor-portal.decision.deadline.label'))
                    ->schema([
                        TextEntry::make('response_deadline')
                            ->label(__('vendor-portal.decision.deadline.label'))
                            ->dateTime('d M Y H:i')
                            ->default('—'),
                        TextEntry::make('deadline_status')
                            ->label(__('vendor-portal.decision.deadline.status'))
                            ->getStateUsing(function () {
                                $deadline = $this->getRecord()->response_deadline;
                                if ($deadline === null) {
                                    return '—';
                                }
                                if ($deadline->isPast()) {
                                    return __('vendor-portal.decision.deadline.expired');
                                }
                                if ($deadline->diffInHours(now()) < 2) {
                                    return __('vendor-portal.decision.deadline.urgent');
                                }

                                return $deadline->diffForHumans();
                            }),
                    ]),

                // Items
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
                                        ->color(fn ($state) => match ($state?->value ?? $state) {
                                            'rental' => 'warning',
                                            'sale' => 'success',
                                            'digital' => 'info',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('quantity')
                                        ->label(__('vendor-portal.bookings.quantity')),
                                    TextEntry::make('line_total_minor')
                                        ->label(__('vendor-portal.bookings.line_total'))
                                        ->money('EGP', divideBy: 100),
                                ]),
                            ]),
                    ]),

                // Customer notes
                Section::make(__('vendor-portal.decision.customer_notes'))
                    ->schema([
                        TextEntry::make('customer_notes_text')
                            ->label('')
                            ->getStateUsing(fn (): string => $this->getRecord()->booking?->customerNotes->first()?->body ?? '—'),
                    ]),

                // Payment status
                Section::make(__('vendor-portal.decision.payment_status.heading'))
                    ->schema([
                        TextEntry::make('booking.payment_status')
                            ->label(__('booking.columns.payment_status'))
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => is_object($state) && method_exists($state, 'getValue')
                                ? $state->getValue()
                                : (string) $state)
                            ->color(fn (mixed $state): string => match (is_object($state) && method_exists($state, 'getValue') ? $state->getValue() : (string) $state) {
                                'paid' => 'success',
                                'partial' => 'warning',
                                'refund_pending' => 'info',
                                default => 'danger',
                            }),
                    ]),

                // Inventory conflicts warning (conditional)
                Section::make(__('vendor-portal.decision.inventory.heading'))
                    ->visible(fn () => ! empty($this->inventoryConflicts))
                    ->description(__('vendor-portal.decision.inventory.warning'))
                    ->schema([
                        TextEntry::make('conflicts_text')
                            ->label('')
                            ->getStateUsing(fn () => implode(', ', $this->inventoryConflicts)),
                    ]),

                // Previous modifications (conditional)
                Section::make(__('vendor-portal.decision.previous_modifications.heading'))
                    ->visible(fn () => $this->getRecord()->modifications?->isNotEmpty() ?? false)
                    ->schema([
                        RepeatableEntry::make('modifications')
                            ->label('')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('created_at')
                                        ->label(__('vendor-portal.decision.previous_modifications.date'))
                                        ->dateTime('d M Y H:i'),
                                    TextEntry::make('status')
                                        ->label(__('vendor-portal.decision.previous_modifications.status'))
                                        ->badge(),
                                ]),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('accept')
                ->label(__('vendor-portal.decision.accept'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('vendor-portal.bookings.accept_confirm'))
                ->visible(fn (): bool => ! $this->isReadOnly)
                ->action(function (): void {
                    app(VendorAcceptBookingAction::class)->execute(new VendorAcceptDTO(
                        bookingVendorId: $this->getRecord()->id,
                        vendorProfileId: $this->getVendorProfile()->id,
                        proposedByUserId: (int) auth()->id(),
                    ));
                    Notification::make()
                        ->title(__('vendor-portal.bookings.accepted'))
                        ->success()
                        ->send();
                    $this->redirect(VendorIncomingBookingsPage::getUrl());
                }),

            Action::make('modify')
                ->label(__('vendor-portal.decision.modify'))
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (): bool => ! $this->isReadOnly)
                ->action(function (): void {
                    $this->redirect(VendorBookingModificationBuilder::getUrl([
                        'bookingVendor' => $this->bookingVendor,
                    ]));
                }),

            Action::make('reject')
                ->label(__('vendor-portal.decision.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => ! $this->isReadOnly)
                ->form([
                    Textarea::make('reason_en')
                        ->label(__('vendor-portal.bookings.reject_reason').' (EN)')
                        ->rows(3),
                    Textarea::make('reason_ar')
                        ->label(__('vendor-portal.bookings.reject_reason').' (AR)')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    $reason = null;
                    if (! empty($data['reason_en']) || ! empty($data['reason_ar'])) {
                        $reason = ['en' => $data['reason_en'] ?? '', 'ar' => $data['reason_ar'] ?? ''];
                    }
                    app(VendorRejectBookingAction::class)->execute(new VendorRejectDTO(
                        bookingVendorId: $this->getRecord()->id,
                        vendorProfileId: $this->getVendorProfile()->id,
                        proposedByUserId: (int) auth()->id(),
                        rejectionReason: $reason,
                    ));
                    Notification::make()
                        ->title(__('vendor-portal.bookings.rejected'))
                        ->warning()
                        ->send();
                    $this->redirect(VendorIncomingBookingsPage::getUrl());
                }),

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
                ->with([
                    'booking.customer',
                    'booking.address',
                    'modifications' => fn ($q) => $q->latest(),
                    'items.service',
                ])
                ->firstOrFail();
        }

        return $this->resolvedBookingVendor;
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }

    private function resolveCoverage(): void
    {
        $record = $this->getRecord();
        $address = $record->booking?->address;

        if ($address === null) {
            $this->isAddressInCoverage = false;

            return;
        }

        $vendor = $this->getVendorProfile();

        $this->isAddressInCoverage = VendorCoverageArea::query()
            ->where('vendor_profile_id', $vendor->id)
            ->where('city_id', $address->city_id)
            ->exists();
    }

    private function resolveInventory(): void
    {
        $record = $this->getRecord();
        $booking = $record->booking;

        if ($booking === null) {
            return;
        }

        $rentalItems = $record->items->filter(
            fn ($item) => $item->product_type === ProductType::Rental
        );

        if ($rentalItems->isEmpty()) {
            return;
        }

        $eventStartsAt = $booking->event_starts_at;
        $eventEndsAt = $booking->event_ends_at;

        if ($eventStartsAt === null) {
            return;
        }

        $rentalServiceIds = $rentalItems->pluck('service_id')->filter()->unique()->values();

        // Find conflicting reservations for these services in the same time window
        // that belong to other booking items (not the current booking's items)
        $currentItemIds = $record->items->pluck('id')->filter()->values()->all();

        $conflictingServiceIds = DB::table('service_inventory_reservations')
            ->whereIn('service_id', $rentalServiceIds)
            ->whereIn('status', ['held', 'confirmed'])
            ->when($currentItemIds, fn ($q) => $q->whereNotIn('booking_item_id', $currentItemIds))
            ->when(
                $eventEndsAt !== null,
                fn ($q) => $q->where('reserved_starts_at', '<', $eventEndsAt)
                    ->where('reserved_ends_at', '>', $eventStartsAt),
                fn ($q) => $q->where('reserved_starts_at', '<', $eventStartsAt->copy()->addHours(1))
                    ->where('reserved_ends_at', '>', $eventStartsAt),
            )
            ->pluck('service_id')
            ->unique()
            ->values()
            ->all();

        if (empty($conflictingServiceIds)) {
            return;
        }

        $locale = app()->getLocale();

        foreach ($rentalItems as $item) {
            if (in_array($item->service_id, $conflictingServiceIds, true)) {
                $nameSnapshot = $item->name_snapshot;
                $this->inventoryConflicts[] = is_array($nameSnapshot)
                    ? ($nameSnapshot[$locale] ?? $nameSnapshot['en'] ?? '—')
                    : '—';
            }
        }
    }

    private function resolveLocks(): void
    {
        $booking = $this->getRecord()->booking;

        if ($booking === null) {
            $this->isLocked = false;

            return;
        }

        $this->isLocked = BookingLock::query()
            ->where('resource_type', 'booking')
            ->where('resource_id', $booking->id)
            ->whereNull('released_at')
            ->exists();
    }

    private function resolveReadOnlyState(): void
    {
        $record = $this->getRecord();
        $booking = $record->booking;

        if ($record->sub_status !== VendorSubStatus::Pending) {
            $this->isReadOnly = true;
            $this->readOnlyReason = 'already_decided';

            return;
        }

        if ($record->response_deadline !== null && $record->response_deadline->isPast()) {
            $this->isReadOnly = true;
            $this->readOnlyReason = 'deadline_expired';

            return;
        }

        if ($booking !== null && $booking->lifecycle_status instanceof CancelledState) {
            $this->isReadOnly = true;
            $this->readOnlyReason = 'cancelled';

            return;
        }

        if ($booking !== null && $booking->lifecycle_status instanceof CompletedState) {
            $this->isReadOnly = true;
            $this->readOnlyReason = 'completed';

            return;
        }

        if ($this->isLocked) {
            $this->isReadOnly = true;
            $this->readOnlyReason = 'locked';

            return;
        }

        if ($record->items->isEmpty()) {
            $this->isReadOnly = true;
            $this->readOnlyReason = 'no_items';
        }
    }
}
