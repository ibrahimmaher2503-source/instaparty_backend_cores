<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\Pages;

use App\Modules\Booking\Application\Actions\ForceCancelBookingAction;
use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Filament\Resources\BookingResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing([
            'customer',
            'occasion',
            'address.city',
            'vendors.vendor',
            'items.service',
            'payments',
            'snapshots.triggeredBy',
            'stateTransitions.triggeredByUser',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label(__('booking.actions.edit'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true)
                ->modalHeading(__('booking.actions.edit'))
                ->modalSubmitActionLabel(__('booking.actions.save_changes'))
                ->form([
                    TextInput::make('guest_count')
                        ->label(__('booking.columns.guest_count'))
                        ->numeric()
                        ->minValue(1)
                        ->default(fn (): ?int => $this->record->guest_count)
                        ->required(),
                    DateTimePicker::make('event_starts_at')
                        ->label(__('booking.columns.event_starts_at'))
                        ->seconds(false)
                        ->default(fn (): mixed => $this->record->event_starts_at)
                        ->required(),
                    DateTimePicker::make('event_ends_at')
                        ->label(__('booking.columns.event_ends_at'))
                        ->seconds(false)
                        ->default(fn (): mixed => $this->record->event_ends_at)
                        ->required(),
                ])
                ->action(function (Booking $record, array $data): void {
                    $record->update([
                        'guest_count' => $data['guest_count'],
                        'event_starts_at' => $data['event_starts_at'],
                        'event_ends_at' => $data['event_ends_at'],
                    ]);

                    Notification::make()
                        ->title(__('booking.notifications.booking_updated'))
                        ->success()
                        ->send();
                }),

            Action::make('forceCancel')
                ->label(__('booking.actions.force_cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('booking.modals.force_cancel_title'))
                ->modalDescription(__('booking.modals.force_cancel_description'))
                ->form([
                    Textarea::make('reason')
                        ->label(__('booking.modals.force_cancel_reason'))
                        ->required()
                        ->minLength(10)
                        ->maxLength(1000),
                ])
                ->visible(fn (Booking $record): bool => auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true
                    && $record->lifecycle_status !== LifecycleStatus::Completed)
                ->action(function (Booking $record, array $data): void {
                    app(ForceCancelBookingAction::class)->execute(
                        $record,
                        new AdminInterventionDTO(
                            bookingId: $record->id,
                            adminId: (int) auth()->id(),
                            interventionType: InterventionType::ForceCancel,
                            reason: $data['reason'],
                        ),
                    );

                    Notification::make()
                        ->title(__('booking.notifications.force_cancelled'))
                        ->success()
                        ->send();
                }),

            Action::make('addAdminNote')
                ->label(__('booking.actions.add_admin_note'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->form([
                    Textarea::make('note')
                        ->label(__('booking.modals.admin_note_body'))
                        ->required()
                        ->minLength(3)
                        ->maxLength(1000)
                        ->rows(5),
                ])
                ->visible(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true)
                ->action(function (Booking $record, array $data): void {
                    $state = [
                        'lifecycle_status' => $record->lifecycle_status->getValue(),
                        'payment_status' => $record->payment_status->getValue(),
                        'fulfillment_status' => $record->fulfillment_status->value,
                    ];

                    BookingAdminIntervention::create([
                        'public_id' => (string) Str::ulid(),
                        'booking_id' => $record->id,
                        'admin_id' => (int) auth()->id(),
                        'intervention_type' => InterventionType::AdminNote,
                        'reason' => $data['note'],
                        'before_state' => $state,
                        'after_state' => $state,
                    ]);

                    Notification::make()
                        ->title(__('booking.notifications.admin_note_added'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
