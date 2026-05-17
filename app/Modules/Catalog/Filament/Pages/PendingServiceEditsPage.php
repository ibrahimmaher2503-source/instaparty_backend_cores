<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Pages;

use App\Modules\Catalog\Application\Actions\ApproveServiceChangeRequestAction;
use App\Modules\Catalog\Application\Actions\RejectServiceChangeRequestAction;
use App\Modules\Catalog\Application\Actions\RequestServiceChangeClarificationAction;
use App\Modules\Catalog\Application\DTOs\DecideServiceChangeRequestDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\Policies\ServiceEditApprovalPolicy;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Http\Exceptions\HttpResponseException;

class PendingServiceEditsPage extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.services');
    }

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?int $navigationSort = 8;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.pending_service_edits');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ServiceChangeRequest::query()
            ->whereIn('status', [
                ServiceChangeRequestStatus::Pending->value,
                ServiceChangeRequestStatus::AwaitingClarification->value,
            ])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected static string $view = 'catalog::filament.pages.pending-service-edits';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ServiceChangeRequest::query()
                    ->with(['service', 'vendorProfile', 'items'])
                    ->open()
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product_type')
                    ->badge()
                    ->color(fn (ProductType $state): string => match ($state) {
                        ProductType::Rental  => 'warning',
                        ProductType::Sale    => 'success',
                        ProductType::Digital => 'info',
                    })
                    ->formatStateUsing(fn (ProductType $state) => $state->label())
                    ->label(__('catalog.product_type')),

                TextColumn::make('vendorProfile.business_name')
                    ->getStateUsing(fn (ServiceChangeRequest $record): string => is_array($record->vendorProfile?->business_name)
                        ? ($record->vendorProfile->business_name['en'] ?? '')
                        : (string) ($record->vendorProfile?->business_name ?? ''))
                    ->searchable(false)
                    ->label(__('catalog.vendor')),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label(__('catalog.service_change_request_items_count'))
                    ->alignCenter(),

                TextColumn::make('clarification_round')
                    ->label(__('catalog.clarification_round'))
                    ->alignCenter(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ServiceChangeRequestStatus $state): string => match ($state) {
                        ServiceChangeRequestStatus::Pending               => 'warning',
                        ServiceChangeRequestStatus::AwaitingClarification => 'info',
                        default                                           => 'gray',
                    })
                    ->formatStateUsing(fn (ServiceChangeRequestStatus $state) => $state->label())
                    ->label(__('catalog.status_label')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('catalog.submitted_at')),
            ])
            ->filters([
                SelectFilter::make('product_type')
                    ->options(ProductType::class)
                    ->label(__('catalog.product_type')),

                SelectFilter::make('status')
                    ->options([
                        ServiceChangeRequestStatus::Pending->value               => __('catalog.service_change_request_status_pending'),
                        ServiceChangeRequestStatus::AwaitingClarification->value => __('catalog.service_change_request_status_awaiting_clarification'),
                    ])
                    ->label(__('catalog.status_label')),
            ])
            ->actions([
                Action::make('approve')
                    ->label(__('catalog.service_change_request_approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form(fn (ServiceChangeRequest $record): array => [
                        Hidden::make('version')
                            ->default($record->version),
                        Textarea::make('admin_note_en')
                            ->label(__('catalog.admin_note_en'))
                            ->rows(3)
                            ->maxLength(2000),
                        Textarea::make('admin_note_ar')
                            ->label(__('catalog.admin_note_ar'))
                            ->rows(3)
                            ->maxLength(2000)
                            ->dir('rtl'),
                    ])
                    ->action(function (ServiceChangeRequest $record, array $data): void {
                        $adminNote = ($data['admin_note_en'] || $data['admin_note_ar'])
                            ? ['en' => $data['admin_note_en'] ?? '', 'ar' => $data['admin_note_ar'] ?? '']
                            : null;

                        try {
                            app(ApproveServiceChangeRequestAction::class)->execute(
                                $record,
                                new DecideServiceChangeRequestDTO(
                                    adminUserId: (int) auth()->id(),
                                    adminNote:   $adminNote,
                                    version:     (int) $data['version'],
                                )
                            );

                            Notification::make()
                                ->title(__('catalog.service_change_request_approved_successfully'))
                                ->success()
                                ->send();
                        } catch (HttpResponseException) {
                            Notification::make()
                                ->title(__('catalog.service_change_request_version_conflict'))
                                ->body(__('catalog.service_change_request_version_conflict_body'))
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation(false),

                Action::make('reject')
                    ->label(__('catalog.service_change_request_reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form(fn (ServiceChangeRequest $record): array => [
                        Hidden::make('version')
                            ->default($record->version),
                        Textarea::make('admin_note_en')
                            ->label(__('catalog.admin_note_en'))
                            ->required()
                            ->rows(3)
                            ->maxLength(2000),
                        Textarea::make('admin_note_ar')
                            ->label(__('catalog.admin_note_ar'))
                            ->required()
                            ->rows(3)
                            ->maxLength(2000)
                            ->dir('rtl'),
                    ])
                    ->action(function (ServiceChangeRequest $record, array $data): void {
                        $adminNote = ['en' => $data['admin_note_en'], 'ar' => $data['admin_note_ar']];

                        try {
                            app(RejectServiceChangeRequestAction::class)->execute(
                                $record,
                                new DecideServiceChangeRequestDTO(
                                    adminUserId: (int) auth()->id(),
                                    adminNote:   $adminNote,
                                    version:     (int) $data['version'],
                                )
                            );

                            Notification::make()
                                ->title(__('catalog.service_change_request_rejected_successfully'))
                                ->success()
                                ->send();
                        } catch (HttpResponseException) {
                            Notification::make()
                                ->title(__('catalog.service_change_request_version_conflict'))
                                ->body(__('catalog.service_change_request_version_conflict_body'))
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation(false),

                Action::make('requestClarification')
                    ->label(__('catalog.service_change_request_request_clarification'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->form(fn (ServiceChangeRequest $record): array => [
                        Hidden::make('version')
                            ->default($record->version),
                        Textarea::make('admin_note_en')
                            ->label(__('catalog.admin_note_en'))
                            ->required()
                            ->rows(3)
                            ->maxLength(2000),
                        Textarea::make('admin_note_ar')
                            ->label(__('catalog.admin_note_ar'))
                            ->required()
                            ->rows(3)
                            ->maxLength(2000)
                            ->dir('rtl'),
                    ])
                    ->action(function (ServiceChangeRequest $record, array $data): void {
                        $adminNote = ['en' => $data['admin_note_en'], 'ar' => $data['admin_note_ar']];

                        try {
                            app(RequestServiceChangeClarificationAction::class)->execute(
                                $record,
                                new DecideServiceChangeRequestDTO(
                                    adminUserId: (int) auth()->id(),
                                    adminNote:   $adminNote,
                                    version:     (int) $data['version'],
                                )
                            );

                            Notification::make()
                                ->title(__('catalog.service_change_request_clarification_sent'))
                                ->success()
                                ->send();
                        } catch (HttpResponseException) {
                            Notification::make()
                                ->title(__('catalog.service_change_request_version_conflict'))
                                ->body(__('catalog.service_change_request_version_conflict_body'))
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation(false)
                    ->visible(fn (ServiceChangeRequest $record): bool =>
                        $record->clarification_round < ServiceEditApprovalPolicy::MAX_CLARIFICATIONS
                    ),
            ]);
    }
}
