<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Filament\Vendor\Pages;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Application\Actions\RespondToReviewAction;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class VendorReviewsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'engagement';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'vendor-portal.pages.vendor-reviews';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.reviews.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.reviews.title');
    }

    public function table(Table $table): Table
    {
        $vendorProfile = $this->getVendorProfile();

        return $table
            ->query(
                ServiceReview::query()
                    ->whereHas('service', fn (Builder $q) => $q->where('vendor_profile_id', $vendorProfile->id))
                    ->where('moderation_status', ModerationStatus::Approved)
                    ->with(['reviewer', 'service'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('service.name')
                    ->label(__('catalog.name'))
                    ->formatStateUsing(fn (ServiceReview $record) => $record->service?->getTranslation('name', app()->getLocale()) ?? '—')
                    ->limit(30),
                TextColumn::make('rating')
                    ->label(__('vendor-portal.reviews.rating'))
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('body')
                    ->label(__('vendor-portal.reviews.body'))
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('reviewer.name')
                    ->label(__('vendor-portal.bookings.customer')),
                TextColumn::make('created_at')
                    ->label(__('vendor-portal.services.last_modified'))
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->actions([
                TableAction::make('respond')
                    ->label(__('vendor-portal.reviews.respond'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->form([
                        Textarea::make('body')
                            ->label(__('vendor-portal.reviews.body'))
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (ServiceReview $record, array $data): void {
                        app(RespondToReviewAction::class)->execute(
                            reviewId: $record->id,
                            reviewType: ReviewType::Service,
                            vendorProfile: $this->getVendorProfile(),
                            body: $data['body'],
                            locale: app()->getLocale(),
                        );
                        Notification::make()->title(__('vendor-portal.reviews.response_submitted'))->success()->send();
                    }),
            ]);
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
