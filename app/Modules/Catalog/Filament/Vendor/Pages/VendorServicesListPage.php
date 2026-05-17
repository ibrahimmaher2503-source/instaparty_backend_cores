<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Pages;

use App\Modules\Catalog\Application\Actions\CloneServiceAction;
use App\Modules\Catalog\Application\Actions\SubmitServiceForReviewAction;
use App\Modules\Catalog\Application\Actions\VendorArchiveServiceAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ServiceState;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class VendorServicesListPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'services';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'vendor-portal.pages.vendor-services-list';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.services.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.services.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Service::query()
                    ->where('vendor_profile_id', $this->getVendorProfile()->id)
                    ->latest()
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->formatStateUsing(fn (Service $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('product_type')
                    ->label(__('vendor-portal.services.type'))
                    ->badge()
                    ->color(fn (ProductType $state) => match ($state) {
                        ProductType::Rental => 'warning',
                        ProductType::Sale => 'success',
                        ProductType::Digital => 'info',
                    })
                    ->formatStateUsing(fn (ProductType $state) => ucfirst($state->value)),
                TextColumn::make('status')
                    ->label(__('vendor-portal.services.status'))
                    ->badge()
                    ->color(fn (mixed $state): string => $state instanceof ServiceState
                        ? (ServiceStatus::tryFrom($state->getValue())?->color() ?? 'gray')
                        : 'gray')
                    ->formatStateUsing(function (mixed $state): string {
                        if (! ($state instanceof ServiceState)) {
                            return (string) $state;
                        }
                        return match (ServiceStatus::tryFrom($state->getValue())) {
                            ServiceStatus::Draft => __('vendor-portal.services.draft'),
                            ServiceStatus::PendingReview => __('vendor-portal.services.pending_review'),
                            ServiceStatus::Published => __('vendor-portal.services.published'),
                            ServiceStatus::Rejected => __('vendor-portal.services.rejected'),
                            ServiceStatus::ChangesRequested => 'Changes Requested',
                            ServiceStatus::Archived => 'Archived',
                            default => $state->getValue(),
                        };
                    }),
                TextColumn::make('base_price_minor')
                    ->label('Price')
                    ->money('EGP', divideBy: 100),
                TextColumn::make('updated_at')
                    ->label(__('vendor-portal.services.last_modified'))
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('product_type')
                    ->label(__('vendor-portal.services.type'))
                    ->options([
                        ProductType::Rental->value => 'Rental',
                        ProductType::Sale->value => 'Sale',
                        ProductType::Digital->value => 'Digital',
                    ]),
                SelectFilter::make('status')
                    ->label(__('vendor-portal.services.status'))
                    ->options([
                        ServiceStatus::Draft->value => __('vendor-portal.services.draft'),
                        ServiceStatus::PendingReview->value => __('vendor-portal.services.pending_review'),
                        ServiceStatus::Published->value => __('vendor-portal.services.published'),
                        ServiceStatus::Rejected->value => __('vendor-portal.services.rejected'),
                    ]),
            ])
            ->actions([
                TableAction::make('submit')
                    ->label(__('vendor-portal.services.submit_for_review'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (Service $record) => in_array($record->status, [ServiceStatus::Draft, ServiceStatus::ChangesRequested], true))
                    ->action(function (Service $record): void {
                        app(SubmitServiceForReviewAction::class)->execute($record, $this->getVendorProfile());
                        Notification::make()->title(__('vendor-portal.services.submitted'))->success()->send();
                    }),

                TableAction::make('clone')
                    ->label(__('vendor-portal.services.clone'))
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (Service $record): void {
                        $clone = app(CloneServiceAction::class)->execute($record, $this->getVendorProfile());
                        Notification::make()->title(__('vendor-portal.services.cloned'))->success()->send();
                    }),

                TableAction::make('archive')
                    ->label(__('vendor-portal.services.archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Service $record) => $record->status !== ServiceStatus::Archived)
                    ->action(function (Service $record): void {
                        app(VendorArchiveServiceAction::class)->execute($record, $this->getVendorProfile());
                        Notification::make()->title(__('vendor-portal.services.archived'))->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkSubmit')
                        ->label(__('vendor-portal.services.submit_for_review'))
                        ->action(function (Collection $records): void {
                            $profile = $this->getVendorProfile();
                            $action = app(SubmitServiceForReviewAction::class);
                            $count = 0;

                            foreach ($records as $service) {
                                try {
                                    $action->execute($service, $profile);
                                    $count++;
                                } catch (\Throwable) {
                                    // Skip invalid transitions
                                }
                            }

                            Notification::make()->title("$count service(s) submitted for review.")->success()->send();
                        }),

                    BulkAction::make('bulkArchive')
                        ->label(__('vendor-portal.services.archive'))
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $profile = $this->getVendorProfile();
                            $action = app(VendorArchiveServiceAction::class);
                            $count = 0;

                            foreach ($records as $service) {
                                if ($service->status === ServiceStatus::Archived) {
                                    continue;
                                }

                                try {
                                    $action->execute($service, $profile);
                                    $count++;
                                } catch (\Throwable) {
                                    // Skip services that cannot transition to archived
                                }
                            }

                            Notification::make()->title("$count service(s) archived.")->success()->send();
                        }),
                ]),
            ]);
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
