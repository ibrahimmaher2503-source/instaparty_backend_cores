<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\CustomerResource\Pages;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Application\Actions\AdminUpdateCustomerProfileAction;
use App\Modules\Identity\Application\Actions\ForceLogoutCustomerAction;
use App\Modules\Identity\Application\Actions\SuspendCustomerAction;
use App\Modules\Identity\Application\Actions\UnsuspendCustomerAction;
use App\Modules\Identity\Application\DTOs\AdminUpdateCustomerDTO;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Resources\CustomerResource;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Tabs::make('Customer')
                ->tabs([
                    Tab::make(__('identity.tabs.overview'))
                        ->schema($this->overviewTab()),

                    Tab::make(__('identity.tabs.bookings'))
                        ->schema($this->bookingsTab()),

                    Tab::make(__('identity.tabs.reviews'))
                        ->schema($this->reviewsTab()),

                    Tab::make(__('identity.tabs.wallet'))
                        ->schema($this->walletTab()),

                    Tab::make(__('identity.tabs.addresses'))
                        ->schema($this->addressesTab()),

                    Tab::make(__('identity.tabs.activity'))
                        ->schema($this->activityTab()),
                ])
                ->columnSpanFull(),
        ]);
    }

    /** @return array<int, mixed> */
    private function overviewTab(): array
    {
        return [
            Section::make(__('identity.sections.identity'))
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('name')
                            ->label(__('identity.columns.name')),
                        TextEntry::make('email')
                            ->label(__('identity.columns.email')),
                        TextEntry::make('phone_e164')
                            ->label(__('identity.columns.phone')),
                        TextEntry::make('status')
                            ->label(__('identity.columns.status'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'suspended' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('preferred_locale')
                            ->label('Locale'),
                        TextEntry::make('last_login_at')
                            ->label('Last Login')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Joined')
                            ->dateTime(),
                        TextEntry::make('customerProfile.date_of_birth')
                            ->label(__('identity.columns.date_of_birth'))
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('customerProfile.gender')
                            ->label(__('identity.columns.gender'))
                            ->badge()
                            ->placeholder('—'),
                        IconEntry::make('customerProfile.accepts_marketing')
                            ->label(__('identity.columns.accepts_marketing'))
                            ->boolean(),
                    ]),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function bookingsTab(): array
    {
        /** @var User $record */
        $record = $this->record;

        $bookings = Booking::query()
            ->where('user_id', $record->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        if ($bookings->isEmpty()) {
            return [
                TextEntry::make('no_bookings')
                    ->state('No bookings yet.')
                    ->hiddenLabel(),
            ];
        }

        $rows = $bookings->map(function (Booking $b): string {
            return sprintf(
                '%s | %s | %s / %s / %s | %s EGP',
                $b->created_at?->format('Y-m-d'),
                $b->public_id,
                $b->lifecycle_status->value,
                $b->payment_status->value,
                $b->fulfillment_status->value,
                number_format($b->total_minor / 100, 2),
            );
        })->implode("\n");

        return [
            TextEntry::make('bookings_list')
                ->state($rows)
                ->hiddenLabel()
                ->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function reviewsTab(): array
    {
        /** @var User $record */
        $record = $this->record;

        $serviceReviews = ServiceReview::query()
            ->where('user_id', $record->id)
            ->orderByDesc('created_at')
            ->get();

        $vendorReviews = VendorReview::query()
            ->where('user_id', $record->id)
            ->orderByDesc('created_at')
            ->get();

        if ($serviceReviews->isEmpty() && $vendorReviews->isEmpty()) {
            return [
                TextEntry::make('no_reviews')
                    ->state('No reviews yet.')
                    ->hiddenLabel(),
            ];
        }

        $rows = collect();

        foreach ($serviceReviews as $r) {
            $rows->push(sprintf('⭐ %d/5 — Service #%s — %s — %s', $r->rating, $r->service_id, mb_substr((string) ($r->body[app()->getLocale()] ?? ''), 0, 80), $r->created_at?->format('Y-m-d')));
        }

        foreach ($vendorReviews as $r) {
            $rows->push(sprintf('⭐ %d/5 — Vendor #%s — %s — %s', $r->rating, $r->vendor_profile_id, mb_substr((string) ($r->body[app()->getLocale()] ?? ''), 0, 80), $r->created_at?->format('Y-m-d')));
        }

        return [
            TextEntry::make('reviews_list')
                ->state($rows->implode("\n"))
                ->hiddenLabel()
                ->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function walletTab(): array
    {
        /** @var User $record */
        $record = $this->record;

        $balances = DB::table('loyalty_ledger')
            ->join('loyalty_programs', 'loyalty_programs.id', '=', 'loyalty_ledger.loyalty_program_id')
            ->where('loyalty_ledger.customer_id', $record->id)
            ->groupBy('loyalty_ledger.loyalty_program_id', 'loyalty_programs.id')
            ->select([
                'loyalty_programs.id as program_id',
                DB::raw('SUM(loyalty_ledger.points) as balance'),
            ])
            ->get();

        if ($balances->isEmpty()) {
            return [
                TextEntry::make('no_wallet')
                    ->state('No loyalty balances yet.')
                    ->hiddenLabel(),
            ];
        }

        $programIds = $balances->pluck('program_id');
        $programs = LoyaltyProgram::whereIn('id', $programIds)->get()->keyBy('id');

        $rows = $balances->map(function (object $row) use ($programs): string {
            $program = $programs->get($row->program_id);
            $name = $program ? ($program->name[app()->getLocale()] ?? $program->name['en'] ?? 'Program #'.$row->program_id) : 'Program #'.$row->program_id;

            return sprintf('%s: %d pts', $name, $row->balance);
        })->implode("\n");

        return [
            TextEntry::make('wallet_list')
                ->state($rows)
                ->hiddenLabel()
                ->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function addressesTab(): array
    {
        /** @var User $record */
        $record = $this->record;

        $addresses = $record->customerAddresses()->with('city')->get();

        if ($addresses->isEmpty()) {
            return [
                TextEntry::make('no_addresses')
                    ->state('No addresses saved yet.')
                    ->hiddenLabel(),
            ];
        }

        $rows = $addresses->map(function (object $addr): string {
            $city = $addr->city?->name ?? '—';
            $default = $addr->is_default ? ' [Default]' : '';

            return sprintf('%s%s — %s, %s', $addr->label, $default, $addr->address_line, $city);
        })->implode("\n");

        return [
            TextEntry::make('addresses_list')
                ->state($rows)
                ->hiddenLabel()
                ->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function activityTab(): array
    {
        /** @var User $record */
        $record = $this->record;

        $entries = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $record->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($entries->isEmpty()) {
            return [
                TextEntry::make('no_activity')
                    ->state('No activity recorded yet.')
                    ->hiddenLabel(),
            ];
        }

        $rows = $entries->map(function (Activity $entry): string {
            $causer = $entry->causer?->name ?? 'system';

            return sprintf('[%s] %s — by %s', $entry->created_at?->format('Y-m-d H:i'), $entry->description, $causer);
        })->implode("\n");

        return [
            TextEntry::make('activity_list')
                ->state($rows)
                ->hiddenLabel()
                ->columnSpanFull(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->editProfileAction(),
            $this->suspendAction(),
            $this->unsuspendAction(),
            $this->forceLogoutAction(),
        ];
    }

    private function editProfileAction(): Action
    {
        return Action::make('edit_profile')
            ->label(__('identity.actions.edit_profile'))
            ->icon('heroicon-o-pencil')
            ->color('primary')
            ->form([
                TextInput::make('name')
                    ->label(__('identity.fields.name'))
                    ->default(fn () => $this->record->name)
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone_e164')
                    ->label(__('identity.fields.phone'))
                    ->default(fn () => $this->record->phone_e164)
                    ->nullable()
                    ->tel()
                    ->helperText('E.164 format: +201001234567'),
            ])
            ->action(function (array $data): void {
                $dto = new AdminUpdateCustomerDTO(
                    name: $data['name'],
                    phoneE164: $data['phone_e164'] ?: null,
                );

                app(AdminUpdateCustomerProfileAction::class)->execute($this->record, $dto);

                Notification::make()
                    ->title(__('identity.notifications.profile_updated_by_admin'))
                    ->success()
                    ->send();

                $this->refreshFormData(['name', 'phone_e164']);
            })
            ->visible(fn () => auth()->user()?->can('update_customer_profile'));
    }

    private function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label(__('identity.actions.suspend_customer'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn () => 'Are you sure you want to suspend '.$this->record->name.'? They will be logged out immediately.')
            ->action(function (): void {
                app(SuspendCustomerAction::class)->execute($this->record);

                Notification::make()
                    ->title(__('identity.notifications.customer_suspended'))
                    ->success()
                    ->send();

                $this->redirect(CustomerResource::getUrl('view', ['record' => $this->record]));
            })
            ->visible(fn () => $this->record->status === 'active'
                && $this->record->id !== auth()->id()
                && auth()->user()?->can('suspend_customer'));
    }

    private function unsuspendAction(): Action
    {
        return Action::make('unsuspend')
            ->label(__('identity.actions.unsuspend_customer'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (): void {
                app(UnsuspendCustomerAction::class)->execute($this->record);

                Notification::make()
                    ->title(__('identity.notifications.customer_unsuspended'))
                    ->success()
                    ->send();

                $this->redirect(CustomerResource::getUrl('view', ['record' => $this->record]));
            })
            ->visible(fn () => $this->record->status === 'suspended'
                && auth()->user()?->can('suspend_customer'));
    }

    private function forceLogoutAction(): Action
    {
        return Action::make('force_logout')
            ->label(__('identity.actions.force_logout'))
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(fn () => 'This will revoke all active sessions for '.$this->record->name.'.')
            ->action(function (): void {
                app(ForceLogoutCustomerAction::class)->execute($this->record);

                Notification::make()
                    ->title(__('identity.notifications.customer_logged_out'))
                    ->success()
                    ->send();
            })
            ->visible(fn () => auth()->user()?->can('force_logout_customer'));
    }
}
