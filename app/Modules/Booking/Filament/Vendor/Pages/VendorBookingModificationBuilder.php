<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Vendor\Pages;

use App\Modules\Booking\Application\Actions\AddBookingModificationItemAction;
use App\Modules\Booking\Application\Actions\CreateBookingModificationAction;
use App\Modules\Booking\Application\Actions\DiscardBookingModificationDraftAction;
use App\Modules\Booking\Application\Actions\SubmitBookingModificationProposalAction;
use App\Modules\Booking\Application\DTOs\AddBookingModificationItemDTO;
use App\Modules\Booking\Application\DTOs\CreateBookingModificationDTO;
use App\Modules\Booking\Application\DTOs\SubmitBookingModificationProposalDTO;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Exceptions\BookingLockedException;
use App\Modules\Booking\Domain\Exceptions\BookingNotModifiableException;
use App\Modules\Booking\Domain\Exceptions\PaymentAlreadyCapturedException;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

class VendorBookingModificationBuilder extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'vendor-portal.pages.vendor-booking-modification-builder';

    public string $bookingVendor;

    private ?BookingVendor $resolvedBookingVendor = null;

    private ?BookingModification $draft = null;

    private bool $isReadOnly = false;

    private string $readOnlyReason = '';

    public function mount(string $bookingVendor): void
    {
        abort_if(auth()->user()->vendorProfile === null, 403);

        $record = BookingVendor::query()
            ->where('public_id', $bookingVendor)
            ->with(['booking', 'items.service'])
            ->firstOrFail();

        abort_if($record->vendor_profile_id !== $this->getVendorProfile()->id, 403);

        $this->bookingVendor = $bookingVendor;
        $this->resolvedBookingVendor = $record;

        try {
            $this->draft = app(CreateBookingModificationAction::class)->execute(new CreateBookingModificationDTO(
                bookingVendorId: $record->id,
                vendorProfileId: $this->getVendorProfile()->id,
                proposedByUserId: (int) auth()->id(),
            ));
        } catch (PaymentAlreadyCapturedException) {
            $this->markReadOnly('payment_captured');
        } catch (BookingLockedException) {
            $this->markReadOnly('locked');
        } catch (BookingNotModifiableException $exception) {
            $this->markReadOnly($exception->reasonKey);
        } catch (ResponseDeadlineExpiredException) {
            $this->markReadOnly('deadline_expired');
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.modification_builder.title');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->getRecord())
            ->schema([
                Section::make(__('vendor-portal.modification_builder.readonly.'.$this->readOnlyReason))
                    ->description(__('vendor-portal.modification_builder.readonly.'.$this->readOnlyReason))
                    ->visible(fn () => $this->isReadOnly && $this->readOnlyReason !== '')
                    ->schema([])
                    ->extraAttributes(['class' => 'fi-color-warning']),

                Section::make(__('vendor-portal.modification_builder.summary'))
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('booking.reference_no')
                                ->label(__('vendor-portal.bookings.reference')),
                            TextEntry::make('booking.event_starts_at')
                                ->label(__('vendor-portal.bookings.event_date'))
                                ->dateTime('d M Y, H:i'),
                            TextEntry::make('subtotal_minor')
                                ->label(__('vendor-portal.bookings.subtotal'))
                                ->money('EGP', divideBy: 100),
                        ]),
                    ]),

                Section::make(__('vendor-portal.modification_builder.current_items'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('public_id')
                                        ->label(__('vendor-portal.modification_builder.item_ref'))
                                        ->copyable(),
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
                                    TextEntry::make('unit_price_minor')
                                        ->label(__('vendor-portal.bookings.unit_price'))
                                        ->money('EGP', divideBy: 100),
                                ]),
                            ]),
                    ]),

                Section::make(__('vendor-portal.modification_builder.draft_changes'))
                    ->visible(fn () => $this->draft !== null && $this->draft->items()->exists())
                    ->schema([
                        TextEntry::make('draft_summary')
                            ->label('')
                            ->getStateUsing(function () {
                                if ($this->draft === null) {
                                    return '—';
                                }

                                $totals = $this->draft->diff_snapshot['totals'] ?? [];
                                $deltaMinor = (int) ($totals['price_delta_minor'] ?? 0);
                                $itemCount = (int) ($totals['item_count'] ?? 0);

                                return sprintf(
                                    '%d %s · %s %.2f EGP',
                                    $itemCount,
                                    __('vendor-portal.modification_builder.changes'),
                                    $deltaMinor >= 0 ? '+' : '-',
                                    abs($deltaMinor) / 100,
                                );
                            }),

                        RepeatableEntry::make('draft_items')
                            ->label('')
                            ->getStateUsing(function () {
                                if ($this->draft === null) {
                                    return [];
                                }

                                return $this->draft->items()->get()->map(fn ($item) => [
                                    'change_kind' => $item->change_kind->value,
                                    'change_type' => $item->payload['change_type'] ?? null,
                                    'price_delta_minor' => $item->payload['price_delta_minor'] ?? 0,
                                ])->all();
                            })
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('change_kind')
                                        ->label(__('vendor-portal.modification_builder.change_kind'))
                                        ->badge(),
                                    TextEntry::make('change_type')
                                        ->label(__('vendor-portal.modification_builder.change_type'))
                                        ->badge(),
                                    TextEntry::make('price_delta_minor')
                                        ->label(__('vendor-portal.modification_builder.price_delta'))
                                        ->money('EGP', divideBy: 100),
                                ]),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addItemAction(),
            $this->submitAction(),
            $this->discardAction(),
            Action::make('back')
                ->label(__('vendor-portal.back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => VendorBookingDecisionPage::getUrl(['bookingVendor' => $this->bookingVendor])),
        ];
    }

    private function addItemAction(): Action
    {
        return Action::make('addItem')
            ->label(__('vendor-portal.modification_builder.add_change'))
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->visible(fn () => ! $this->isReadOnly && $this->draft !== null)
            ->form([
                Select::make('change_kind')
                    ->label(__('vendor-portal.modification_builder.change_kind'))
                    ->options(collect(ModificationChangeKind::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => $case->value])
                        ->all())
                    ->required()
                    ->live(),
                Select::make('change_type')
                    ->label(__('vendor-portal.modification_builder.change_type'))
                    ->options(collect(ModificationProposalKind::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => $case->value])
                        ->all())
                    ->required(),
                Select::make('target_booking_item_id')
                    ->label(__('vendor-portal.modification_builder.target_item'))
                    ->options(fn () => $this->getRecord()
                        ->items
                        ->mapWithKeys(fn (BookingItem $item) => [
                            $item->id => sprintf(
                                '%s · qty %d',
                                is_array($item->name_snapshot)
                                    ? ($item->name_snapshot[app()->getLocale()]
                                        ?? $item->name_snapshot['en']
                                        ?? '—')
                                    : '—',
                                $item->quantity,
                            ),
                        ])
                        ->all())
                    ->visible(fn ($get) => $get('change_kind') !== ModificationChangeKind::Add->value),
                Select::make('service_id')
                    ->label(__('vendor-portal.modification_builder.new_item_service'))
                    ->options(fn () => Service::query()
                        ->where('vendor_profile_id', $this->getVendorProfile()->id)
                        ->limit(50)
                        ->pluck('id', 'id')
                        ->all())
                    ->visible(fn ($get) => $get('change_kind') === ModificationChangeKind::Add->value),
                Select::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->options(collect(ProductType::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => $case->value])
                        ->all())
                    ->visible(fn ($get) => $get('change_kind') === ModificationChangeKind::Add->value),
                TextInput::make('unit_price_minor')
                    ->label(__('vendor-portal.modification_builder.unit_price_minor'))
                    ->numeric(),
                TextInput::make('quantity')
                    ->label(__('vendor-portal.bookings.quantity'))
                    ->numeric()
                    ->minValue(0),
                DateTimePicker::make('effective_starts_at')
                    ->label(__('vendor-portal.modification_builder.effective_starts_at'))
                    ->seconds(false),
                DateTimePicker::make('effective_ends_at')
                    ->label(__('vendor-portal.modification_builder.effective_ends_at'))
                    ->seconds(false),
                Textarea::make('vendor_note_en')
                    ->label(__('vendor-portal.modification_builder.note').' (EN)')
                    ->rows(2),
                Textarea::make('vendor_note_ar')
                    ->label(__('vendor-portal.modification_builder.note').' (AR)')
                    ->rows(2),
            ])
            ->action(function (array $data): void {
                $payload = array_filter([
                    'service_id' => isset($data['service_id']) ? (int) $data['service_id'] : null,
                    'product_type' => $data['product_type'] ?? null,
                    'unit_price_minor' => isset($data['unit_price_minor']) ? (int) $data['unit_price_minor'] : null,
                    'unit_price_currency' => isset($data['unit_price_minor']) ? 'EGP' : null,
                    'quantity' => isset($data['quantity']) ? (int) $data['quantity'] : null,
                    'effective_starts_at' => $data['effective_starts_at'] ?? null,
                    'effective_ends_at' => $data['effective_ends_at'] ?? null,
                    'vendor_note' => array_filter([
                        'en' => $data['vendor_note_en'] ?? null,
                        'ar' => $data['vendor_note_ar'] ?? null,
                    ]),
                ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

                app(AddBookingModificationItemAction::class)->execute(new AddBookingModificationItemDTO(
                    bookingModificationId: $this->draft->id,
                    vendorProfileId: $this->getVendorProfile()->id,
                    proposedByUserId: (int) auth()->id(),
                    changeKind: ModificationChangeKind::from($data['change_kind']),
                    changeType: ModificationProposalKind::from($data['change_type']),
                    targetBookingItemId: isset($data['target_booking_item_id'])
                        ? (int) $data['target_booking_item_id']
                        : null,
                    payload: $payload,
                ));

                Notification::make()
                    ->title(__('vendor-portal.modification_builder.change_added'))
                    ->success()
                    ->send();

                $this->draft = $this->draft->fresh(['items']);
            });
    }

    private function submitAction(): Action
    {
        return Action::make('submit')
            ->label(__('vendor-portal.modification_builder.submit'))
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->visible(fn () => ! $this->isReadOnly
                && $this->draft !== null
                && $this->draft->items()->exists())
            ->requiresConfirmation()
            ->form([
                Textarea::make('vendor_explanation_en')
                    ->label(__('vendor-portal.modification_builder.explanation').' (EN)')
                    ->rows(3),
                Textarea::make('vendor_explanation_ar')
                    ->label(__('vendor-portal.modification_builder.explanation').' (AR)')
                    ->rows(3),
                DateTimePicker::make('expires_at')
                    ->label(__('vendor-portal.modification_builder.expires_at'))
                    ->seconds(false),
            ])
            ->action(function (array $data): void {
                $explanation = array_filter([
                    'en' => $data['vendor_explanation_en'] ?? null,
                    'ar' => $data['vendor_explanation_ar'] ?? null,
                ]);

                $expiresAt = isset($data['expires_at']) && $data['expires_at'] !== ''
                    ? CarbonImmutable::parse($data['expires_at'])
                    : null;

                app(SubmitBookingModificationProposalAction::class)->execute(
                    new SubmitBookingModificationProposalDTO(
                        bookingModificationId: $this->draft->id,
                        vendorProfileId: $this->getVendorProfile()->id,
                        proposedByUserId: (int) auth()->id(),
                        vendorExplanation: $explanation,
                        expiresAt: $expiresAt,
                    ),
                );

                Notification::make()
                    ->title(__('vendor-portal.modification_builder.submitted'))
                    ->success()
                    ->send();

                $this->redirect(VendorIncomingBookingsPage::getUrl());
            });
    }

    private function discardAction(): Action
    {
        return Action::make('discard')
            ->label(__('vendor-portal.modification_builder.discard'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn () => ! $this->isReadOnly && $this->draft !== null)
            ->requiresConfirmation()
            ->modalHeading(__('vendor-portal.modification_builder.discard_confirm'))
            ->action(function (): void {
                app(DiscardBookingModificationDraftAction::class)->execute(
                    $this->draft,
                    (int) auth()->id(),
                    $this->getVendorProfile()->id,
                );

                Notification::make()
                    ->title(__('vendor-portal.modification_builder.discarded'))
                    ->success()
                    ->send();

                $this->redirect(VendorBookingDecisionPage::getUrl(['bookingVendor' => $this->bookingVendor]));
            });
    }

    private function getRecord(): BookingVendor
    {
        if ($this->resolvedBookingVendor === null) {
            $this->resolvedBookingVendor = BookingVendor::query()
                ->where('public_id', $this->bookingVendor)
                ->with(['booking', 'items.service'])
                ->firstOrFail();
        }

        return $this->resolvedBookingVendor;
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }

    private function markReadOnly(string $reason): void
    {
        $this->isReadOnly = true;
        $this->readOnlyReason = $reason;
    }
}
