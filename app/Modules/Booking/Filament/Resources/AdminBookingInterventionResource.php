<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages\ListBookingInterventions;
use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages\ViewBookingIntervention;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminBookingInterventionResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Bookings';

    protected static ?string $navigationLabel = 'Intervention';

    protected static ?string $slug = 'admin-booking-intervention';

    protected static ?int $navigationSort = 90;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('booking.intervene.access') === true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('bookings.*')
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNotIn('lifecycle_status', ['completed', 'cancelled'])
            ->where(function (Builder $query): void {
                // Trouble bucket 1: late vendor response
                $query->orWhereExists(function ($sub): void {
                    $sub->from('booking_vendors')
                        ->whereColumn('booking_vendors.booking_id', 'bookings.id')
                        ->where('booking_vendors.sub_status', 'pending')
                        ->whereNotNull('booking_vendors.response_deadline')
                        ->where('booking_vendors.response_deadline', '<', now());
                });

                // Trouble bucket 2: all vendors rejected
                $query->orWhereExists(function ($sub): void {
                    $sub->from('booking_vendors as bv_check')
                        ->whereColumn('bv_check.booking_id', 'bookings.id')
                        ->whereNotExists(function ($inner) {
                            $inner->from('booking_vendors as bv_inner')
                                ->whereColumn('bv_inner.booking_id', 'bv_check.booking_id')
                                ->where('bv_inner.sub_status', '!=', 'rejected');
                        });
                });

                // Trouble bucket 3: customer review pending (open modification > 24h)
                $query->orWhereExists(function ($sub): void {
                    $sub->from('booking_modifications')
                        ->join('booking_vendors as bvm', 'bvm.id', '=', 'booking_modifications.booking_vendor_id')
                        ->whereColumn('bvm.booking_id', 'bookings.id')
                        ->where('booking_modifications.status', 'pending')
                        ->where('booking_modifications.created_at', '<', now()->subHours(24));
                });

                // Trouble bucket 4: stalled booking
                $stalledHours = config('booking.intervention.stalled_threshold_hours', 48);
                $query->orWhere(function (Builder $inner) use ($stalledHours): void {
                    $inner->whereIn('lifecycle_status', ['submitted', 'vendor_review'])
                          ->where('submitted_at', '<', now()->subHours($stalledHours));
                });
            });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_no')
                    ->label(__('booking.intervention.columns.reference'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('booking.intervention.columns.customer'))
                    ->searchable()
                    ->sortable()
                    ->default(fn (Booking $record): string => '#'.$record->customer_id),

                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label(__('booking.intervention.columns.lifecycle_status'))
                    ->badge()
                    ->color(fn ($state): string => match (is_string($state) ? $state : ($state?->value ?? '')) {
                        'submitted'       => 'info',
                        'vendor_review'   => 'warning',
                        'customer_review' => 'primary',
                        'confirmed'       => 'success',
                        default           => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label(__('booking.intervention.columns.total'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label(__('booking.intervention.columns.nearest_deadline'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_starts_at')
                    ->label(__('booking.intervention.columns.event_starts_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lifecycle_status')
                    ->label(__('booking.intervention.columns.lifecycle_status'))
                    ->options([
                        'submitted'       => 'Submitted',
                        'vendor_review'   => 'Vendor Review',
                        'customer_review' => 'Customer Review',
                    ]),

                Tables\Filters\SelectFilter::make('product_type')
                    ->label(__('booking.intervention.columns.product_type'))
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }

                        return $query->whereExists(function ($sub) use ($data): void {
                            $sub->from('booking_items')
                                ->join('booking_vendors as bvp', 'bvp.id', '=', 'booking_items.booking_vendor_id')
                                ->whereColumn('bvp.booking_id', 'bookings.id')
                                ->where('booking_items.product_type', $data['value']);
                        });
                    })
                    ->options([
                        'rental'  => 'Rental',
                        'sale'    => 'Sale',
                        'digital' => 'Digital',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('sendVendorReminder')
                    ->label(__('booking::booking.intervention.actions.send_vendor_reminder'))
                    ->icon('heroicon-o-bell')
                    ->color('warning')
                    ->visible(fn (Booking $record): bool =>
                        auth()->user()?->can('booking.intervene.send_vendor_reminder') === true &&
                        $record->vendors()->where('sub_status', 'pending')->exists()
                    )
                    ->form([
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Note (optional)')
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $bookingVendor = $record->vendors()->where('sub_status', 'pending')->first();
                        if (! $bookingVendor) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('booking::booking.intervention.error.vendor_not_pending'))
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            app(\App\Modules\Booking\Application\Actions\SendVendorReminderAction::class)
                                ->execute($bookingVendor, auth()->id(), $data['note'] ?? null);

                            \Filament\Notifications\Notification::make()
                                ->title(__('booking::booking.intervention.success.send_vendor_reminder'))
                                ->success()
                                ->send();
                        } catch (\DomainException $e) {
                            \Filament\Notifications\Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.send_vendor_reminder')),

                Tables\Actions\Action::make('escalateLateVendorResponse')
                    ->label(__('booking::booking.intervention.actions.escalate_vendor_timeout'))
                    ->icon('heroicon-o-clock')
                    ->color('danger')
                    ->visible(fn (Booking $record): bool =>
                        auth()->user()?->can('booking.intervene.escalate_vendor_timeout') === true &&
                        $record->vendors()
                            ->where('sub_status', 'pending')
                            ->where('response_deadline', '<', now())
                            ->exists()
                    )
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Reason for escalation')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $bookingVendor = $record->vendors()
                            ->where('sub_status', 'pending')
                            ->where('response_deadline', '<', now())
                            ->first();

                        if (! $bookingVendor) {
                            \Filament\Notifications\Notification::make()
                                ->title('No vendor with passed deadline found.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            app(\App\Modules\Booking\Application\Actions\EscalateLateVendorResponseAction::class)
                                ->execute($bookingVendor, new \App\Modules\Booking\Application\DTOs\AdminInterventionDTO(
                                    bookingId: $record->id,
                                    adminId: auth()->id(),
                                    interventionType: \App\Modules\Booking\Domain\Enums\InterventionType::VendorTimeout,
                                    reason: $data['reason'],
                                    bookingVendorId: $bookingVendor->id,
                                ));

                            \Filament\Notifications\Notification::make()
                                ->title(__('booking::booking.intervention.success.escalate_vendor_timeout'))
                                ->success()
                                ->send();
                        } catch (\DomainException $e) {
                            \Filament\Notifications\Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.escalate_vendor_timeout')),

                Tables\Actions\Action::make('suggestAlternativeVendors')
                    ->label(__('booking::booking.intervention.actions.suggest_alternative_vendors'))
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->visible(fn (Booking $record): bool =>
                        auth()->user()?->can('booking.intervene.suggest_alternative_vendors') === true
                    )
                    ->form([
                        \Filament\Forms\Components\Select::make('vendor_profile_ids')
                            ->label('Suggest vendors')
                            ->multiple()
                            ->options(function (Booking $record): array {
                                try {
                                    $max = config('booking.intervention.suggest_max_candidates', 5);

                                    return app(\App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder::class)
                                        ->findCandidates($record, $max)
                                        ->pluck('business_name', 'id')
                                        ->map(fn ($name) => is_array($name) ? ($name[app()->getLocale()] ?? $name['en'] ?? '') : $name)
                                        ->toArray();
                                } catch (\Throwable) {
                                    return [];
                                }
                            })
                            ->required()
                            ->maxItems(config('booking.intervention.suggest_max_candidates', 5)),
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(\App\Modules\Booking\Application\Actions\SuggestAlternativeVendorsAction::class)
                                ->execute($record, new \App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO(
                                    bookingId: $record->id,
                                    adminId: auth()->id(),
                                    vendorProfileIds: $data['vendor_profile_ids'],
                                    reason: $data['reason'],
                                ));

                            \Filament\Notifications\Notification::make()
                                ->title(__('booking::booking.intervention.success.suggest_alternative_vendors'))
                                ->success()->send();
                        } catch (\DomainException | \InvalidArgumentException $e) {
                            \Filament\Notifications\Notification::make()
                                ->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.suggest_alternative_vendors')),

                Tables\Actions\Action::make('resumeCustomerReview')
                    ->label(__('booking::booking.intervention.actions.resume_customer_review'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->visible(fn (Booking $record): bool =>
                        auth()->user()?->can('booking.intervene.resume_customer_review') === true &&
                        DB::table('booking_modifications')
                            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_modifications.booking_vendor_id')
                            ->where('booking_vendors.booking_id', $record->id)
                            ->where('booking_modifications.status', 'pending')
                            ->where('booking_modifications.created_at', '<', now()->subHours(24))
                            ->exists()
                    )
                    ->form([
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Note (optional)')
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(\App\Modules\Booking\Application\Actions\ResumeBookingReviewAction::class)
                                ->execute($record, auth()->id(), $data['note'] ?? null);

                            \Filament\Notifications\Notification::make()
                                ->title(__('booking::booking.intervention.success.resume_customer_review'))
                                ->success()->send();
                        } catch (\DomainException $e) {
                            \Filament\Notifications\Notification::make()
                                ->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.resume_customer_review')),

                Tables\Actions\Action::make('createInterventionNote')
                    ->label(__('booking::booking.intervention.actions.create_note'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (): bool => auth()->user()?->can('booking.intervene.create_note') === true)
                    ->form([
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->required()
                            ->minLength(1)
                            ->maxLength(2000)
                            ->rows(4),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(\App\Modules\Booking\Application\Actions\CreateAdminInterventionNoteAction::class)
                                ->execute($record, auth()->id(), $data['note']);

                            \Filament\Notifications\Notification::make()
                                ->title(__('booking::booking.intervention.success.create_note'))
                                ->success()->send();
                        } catch (\InvalidArgumentException $e) {
                            \Filament\Notifications\Notification::make()
                                ->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.create_note')),

                Tables\Actions\Action::make('freezeBookingChat')
                    ->label(__('booking::booking.intervention.actions.freeze_chat'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (Booking $record): bool =>
                        auth()->user()?->can('booking.intervene.freeze_chat') === true &&
                        DB::table('chat_threads')
                            ->where('booking_id', $record->id)
                            ->whereNull('frozen_at')
                            ->exists()
                    )
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Reason for freezing')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(\App\Modules\Booking\Application\Actions\FreezeBookingChatAction::class)
                                ->execute($record->id, auth()->id(), $data['reason']);

                            \Filament\Notifications\Notification::make()
                                ->title(__('booking::booking.intervention.success.freeze_chat'))
                                ->success()->send();
                        } catch (\DomainException $e) {
                            \Filament\Notifications\Notification::make()
                                ->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.freeze_chat')),

                Tables\Actions\Action::make('resumeBookingChat')
                    ->label(__('booking::booking.intervention.actions.resume_chat'))
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (Booking $record): bool =>
                        auth()->user()?->can('booking.intervene.freeze_chat') === true &&
                        DB::table('chat_threads')
                            ->where('booking_id', $record->id)
                            ->whereNotNull('frozen_at')
                            ->exists()
                    )
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Reason for resuming')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(\App\Modules\Booking\Application\Actions\ResumeBookingChatAction::class)
                                ->execute($record->id, auth()->id(), $data['reason']);

                            \Filament\Notifications\Notification::make()
                                ->title(__('booking::booking.intervention.success.resume_chat'))
                                ->success()->send();
                        } catch (\DomainException $e) {
                            \Filament\Notifications\Notification::make()
                                ->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.resume_chat')),
            ])
            ->defaultSort('submitted_at', 'asc')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookingInterventions::route('/'),
            'view'  => ViewBookingIntervention::route('/{record}'),
        ];
    }
}
