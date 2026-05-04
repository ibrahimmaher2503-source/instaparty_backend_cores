<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Filament\Pages;

use App\Modules\Reviews\Application\Actions\ModerateReviewAction;
use App\Modules\Reviews\Application\DTOs\ModerateReviewData;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ReviewModerationPage extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.moderation');
    }

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('reviews.nav.review_moderation');
    }

    protected static string $view = 'reviews::filament.pages.review-moderation';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ServiceReview::query()
                    ->where('moderation_status', ModerationStatus::Pending->value)
                    ->with('reviewer')
            )
            ->columns([
                TextColumn::make('public_id')
                    ->label('ID')
                    ->searchable()
                    ->limit(12),

                TextColumn::make('review_type')
                    ->label(__('reviews.labels.review_type'))
                    ->default('service')
                    ->badge()
                    ->color('info'),

                TextColumn::make('rating')
                    ->label(__('reviews.labels.rating'))
                    ->sortable(),

                TextColumn::make('body')
                    ->label(__('reviews.labels.body'))
                    ->limit(80)
                    ->wrap(),

                TextColumn::make('locale')
                    ->label(__('reviews.labels.locale'))
                    ->badge(),

                TextColumn::make('moderation_status')
                    ->label(__('reviews.labels.moderation_status'))
                    ->badge()
                    ->color(fn (ModerationStatus $state): string => match ($state) {
                        ModerationStatus::Pending => 'warning',
                        ModerationStatus::Approved => 'success',
                        ModerationStatus::Rejected => 'danger',
                        ModerationStatus::Hidden => 'gray',
                    }),

                TextColumn::make('reviewer.name')
                    ->label(__('reviews.labels.reviewer'))
                    ->default('â€”'),

                TextColumn::make('created_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('moderation_status')
                    ->label(__('reviews.labels.moderation_status'))
                    ->options(ModerationStatus::class)
                    ->default(ModerationStatus::Pending->value),

                SelectFilter::make('locale')
                    ->label(__('reviews.labels.locale'))
                    ->options(['en' => 'English', 'ar' => 'Arabic']),
            ])
            ->actions([
                TableAction::make('approve')
                    ->label(__('reviews.actions.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (ServiceReview $record): void {
                        app(ModerateReviewAction::class)->execute(
                            $record,
                            new ModerateReviewData(
                                toStatus: ModerationStatus::Approved,
                                moderatorId: auth()->id(),
                            )
                        );
                        Notification::make()->title(__('reviews.notifications.approved_title'))->success()->send();
                    })
                    ->visible(fn (ServiceReview $record): bool => $record->moderation_status->canTransitionTo(ModerationStatus::Approved)),

                TableAction::make('reject')
                    ->label(__('reviews.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason_en')
                            ->label('Reason (EN)')
                            ->required(),
                        Textarea::make('reason_ar')
                            ->label('Reason (AR)')
                            ->required(),
                    ])
                    ->action(function (ServiceReview $record, array $data): void {
                        app(ModerateReviewAction::class)->execute(
                            $record,
                            new ModerateReviewData(
                                toStatus: ModerationStatus::Rejected,
                                moderatorId: auth()->id(),
                                reason: ['en' => $data['reason_en'], 'ar' => $data['reason_ar']],
                            )
                        );
                        Notification::make()->title(__('reviews.notifications.rejected_title'))->warning()->send();
                    })
                    ->visible(fn (ServiceReview $record): bool => $record->moderation_status->canTransitionTo(ModerationStatus::Rejected)),

                TableAction::make('hide')
                    ->label(__('reviews.actions.hide'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (ServiceReview $record): void {
                        app(ModerateReviewAction::class)->execute(
                            $record,
                            new ModerateReviewData(
                                toStatus: ModerationStatus::Hidden,
                                moderatorId: auth()->id(),
                            )
                        );
                        Notification::make()->title(__('reviews.notifications.hidden_title'))->info()->send();
                    })
                    ->visible(fn (ServiceReview $record): bool => $record->moderation_status->canTransitionTo(ModerationStatus::Hidden)),
            ])
            ->bulkActions([
                BulkAction::make('bulk_approve')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $action = app(ModerateReviewAction::class);
                        foreach ($records as $record) {
                            if ($record->moderation_status->canTransitionTo(ModerationStatus::Approved)) {
                                $action->execute(
                                    $record,
                                    new ModerateReviewData(
                                        toStatus: ModerationStatus::Approved,
                                        moderatorId: auth()->id(),
                                    )
                                );
                            }
                        }
                        Notification::make()->title(__('reviews.notifications.bulk_approved_title'))->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
