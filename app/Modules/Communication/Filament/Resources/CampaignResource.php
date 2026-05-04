<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Application\Actions\CampaignAlreadyDispatchedException;
use App\Modules\Communication\Application\Actions\CampaignCannotBeCancelledException;
use App\Modules\Communication\Application\Actions\CancelCampaignAction;
use App\Modules\Communication\Application\Actions\DispatchCampaignAction;
use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Filament\Resources\CampaignResource\Pages;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.communication');
    }

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('communication.nav.campaigns');
    }

    public static function getModelLabel(): string
    {
        return __('communication.models.campaign.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('communication.models.campaign.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Campaign Name')
                ->required()
                ->maxLength(160)
                ->columnSpanFull(),

            Select::make('channel')
                ->label('Channel')
                ->options(collect(CampaignChannel::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                ->required(),

            Select::make('target_locale')
                ->label('Target Locale')
                ->options(collect(CampaignTargetLocale::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                ->required(),

            KeyValue::make('segment_filters')
                ->label('Segment Filters')
                ->helperText('Allowed keys: booked_product_type (rental|sale|digital), booked_within_days, governorate_id, preferred_locale')
                ->keyLabel('Filter Key')
                ->valueLabel('Value')
                ->required()
                ->columnSpanFull(),

            Tabs::make('Content')
                ->tabs([
                    Tabs\Tab::make('English')
                        ->schema([
                            TextInput::make('subject.en')
                                ->label('Subject (EN)')
                                ->maxLength(255),
                            Textarea::make('body.en')
                                ->label('Body (EN)')
                                ->required()
                                ->rows(4)
                                ->helperText('Available variables: {{first_name}}, {{preferred_locale}}, {{governorate_name_localized}}'),
                        ]),
                    Tabs\Tab::make('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©')
                        ->schema([
                            TextInput::make('subject.ar')
                                ->label('Subject (AR)')
                                ->maxLength(255),
                            Textarea::make('body.ar')
                                ->label('Body (AR)')
                                ->required()
                                ->rows(4),
                        ]),
                ])
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (CampaignChannel $state) => $state->label()),
                TextColumn::make('target_locale')
                    ->badge()
                    ->formatStateUsing(fn (CampaignTargetLocale $state) => $state->label()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CampaignStatus $state) => $state->color())
                    ->formatStateUsing(fn (CampaignStatus $state) => $state->label()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CampaignStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                SelectFilter::make('channel')
                    ->options(collect(CampaignChannel::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->actions([
                Action::make('sendNow')
                    ->label('Send Now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Dispatch Campaign')
                    ->modalDescription('This will immediately send the campaign to all matched recipients. This cannot be undone.')
                    ->visible(fn (Campaign $record) => $record->isDraft())
                    ->action(function (Campaign $record) {
                        try {
                            app(DispatchCampaignAction::class)->execute($record);
                            Notification::make()
                                ->title('Campaign dispatched')
                                ->body('Recipients are being queued for delivery.')
                                ->success()
                                ->send();
                        } catch (CampaignAlreadyDispatchedException $e) {
                            Notification::make()
                                ->title('Campaign already dispatched')
                                ->danger()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Dispatch failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Campaign $record) => in_array($record->status, [CampaignStatus::Draft, CampaignStatus::Running]))
                    ->action(function (Campaign $record) {
                        try {
                            app(CancelCampaignAction::class)->execute($record);
                            Notification::make()
                                ->title('Campaign cancelled')
                                ->success()
                                ->send();
                        } catch (CampaignCannotBeCancelledException $e) {
                            Notification::make()
                                ->title('Cannot cancel')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->visible(fn (Campaign $record) => $record->isDraft()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'view' => Pages\ViewCampaign::route('/{record}'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
