<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages;

use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewBookingIntervention extends ViewRecord
{
    protected static string $resource = AdminBookingInterventionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // ── 1. Booking Summary ──────────────────────────────────────────
            Section::make(__('booking.intervention.sections.summary'))
                ->columns(3)
                ->schema([
                    TextEntry::make('reference_no')
                        ->label(__('booking.intervention.columns.reference'))
                        ->copyable()
                        ->weight('bold'),

                    TextEntry::make('lifecycle_status')
                        ->label(__('booking.intervention.columns.lifecycle_status'))
                        ->badge()
                        ->color(fn ($state): string => match (is_string($state) ? $state : ($state?->value ?? '')) {
                            'submitted'       => 'info',
                            'vendor_review'   => 'warning',
                            'customer_review' => 'primary',
                            'confirmed'       => 'success',
                            default           => 'gray',
                        }),

                    TextEntry::make('payment_status')
                        ->label(__('booking.intervention.columns.payment_status'))
                        ->badge(),

                    TextEntry::make('fulfillment_status')
                        ->label(__('booking.intervention.columns.fulfillment_status'))
                        ->badge(),

                    TextEntry::make('total_minor')
                        ->label(__('booking.intervention.columns.total'))
                        ->money('EGP', divideBy: 100),

                    TextEntry::make('event_starts_at')
                        ->label(__('booking.intervention.columns.event_starts_at'))
                        ->dateTime(),

                    TextEntry::make('event_ends_at')
                        ->label(__('booking.intervention.columns.event_ends_at'))
                        ->dateTime(),

                    TextEntry::make('submitted_at')
                        ->label(__('booking.intervention.columns.submitted_at'))
                        ->dateTime(),

                    TextEntry::make('guest_count')
                        ->label(__('booking.intervention.columns.guest_count')),
                ]),

            // ── 2. Vendors ──────────────────────────────────────────────────
            Section::make(__('booking.intervention.sections.vendors'))
                ->schema([
                    RepeatableEntry::make('vendors')
                        ->label('')
                        ->schema([
                            Grid::make(4)->schema([
                                TextEntry::make('vendor.business_name')
                                    ->label(__('booking.intervention.columns.vendor'))
                                    ->default('—'),

                                TextEntry::make('sub_status')
                                    ->label(__('booking.intervention.columns.sub_status'))
                                    ->badge(),

                                TextEntry::make('response_deadline')
                                    ->label(__('booking.intervention.columns.response_deadline'))
                                    ->dateTime()
                                    ->default('—'),

                                TextEntry::make('responded_at')
                                    ->label(__('booking.intervention.columns.responded_at'))
                                    ->dateTime()
                                    ->default('—'),
                            ]),
                        ]),
                ]),

            // ── 3. Open Modifications ───────────────────────────────────────
            Section::make(__('booking.intervention.sections.modifications'))
                ->schema([
                    RepeatableEntry::make('pendingModifications')
                        ->label('')
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('proposal_kind')
                                    ->label(__('booking.intervention.columns.proposal_kind'))
                                    ->badge(),

                                TextEntry::make('status')
                                    ->label(__('booking.intervention.columns.status'))
                                    ->badge(),

                                TextEntry::make('created_at')
                                    ->label(__('booking.intervention.columns.proposed_at'))
                                    ->dateTime(),
                            ]),
                        ]),
                ]),

            // ── 4. State Transitions ────────────────────────────────────────
            Section::make(__('booking.intervention.sections.state_transitions'))
                ->schema([
                    RepeatableEntry::make('stateTransitions')
                        ->label('')
                        ->schema([
                            Grid::make(4)->schema([
                                TextEntry::make('from_state')
                                    ->label(__('booking.intervention.columns.from_state'))
                                    ->default('—'),

                                TextEntry::make('to_state')
                                    ->label(__('booking.intervention.columns.to_state')),

                                TextEntry::make('actor_type')
                                    ->label(__('booking.intervention.columns.actor_type'))
                                    ->default('—'),

                                TextEntry::make('created_at')
                                    ->label(__('booking.intervention.columns.transitioned_at'))
                                    ->dateTime(),
                            ]),
                        ]),
                ]),

            // ── 5. Payments ─────────────────────────────────────────────────
            Section::make(__('booking.intervention.sections.payments'))
                ->schema([
                    RepeatableEntry::make('payments')
                        ->label('')
                        ->schema([
                            Grid::make(4)->schema([
                                TextEntry::make('gateway')
                                    ->label(__('booking.intervention.columns.gateway')),

                                TextEntry::make('amount_minor')
                                    ->label(__('booking.intervention.columns.amount'))
                                    ->money('EGP', divideBy: 100),

                                TextEntry::make('status')
                                    ->label(__('booking.intervention.columns.status'))
                                    ->badge(),

                                TextEntry::make('created_at')
                                    ->label(__('booking.intervention.columns.paid_at'))
                                    ->dateTime(),
                            ]),
                        ]),
                ]),

            // ── 6. Customer Notes ───────────────────────────────────────────
            Section::make(__('booking.intervention.sections.customer_notes'))
                ->schema([
                    RepeatableEntry::make('customerNotes')
                        ->label('')
                        ->schema([
                            TextEntry::make('note')
                                ->label('')
                                ->columnSpanFull(),
                        ]),
                ]),

            // ── 7. Intervention History ─────────────────────────────────────
            Section::make(__('booking.intervention.sections.intervention_history'))
                ->schema([
                    RepeatableEntry::make('adminInterventions')
                        ->label('')
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('intervention_type')
                                    ->label(__('booking.intervention.columns.intervention_type'))
                                    ->badge(),

                                TextEntry::make('admin.name')
                                    ->label(__('booking.intervention.columns.admin'))
                                    ->default('—'),

                                TextEntry::make('created_at')
                                    ->label(__('booking.intervention.columns.intervened_at'))
                                    ->dateTime(),
                            ]),

                            TextEntry::make('reason')
                                ->label(__('booking.intervention.columns.reason'))
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }
}
