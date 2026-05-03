<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationTemplateResource extends Resource
{
    use Translatable;

    protected static ?string $model = NotificationTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'Communications';

    protected static ?int $navigationSort = 1;

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('communication::resource.notification_templates'))
                ->schema([
                    Forms\Components\TextInput::make('event_key')
                        ->label(__('communication::resource.event_key'))
                        ->required()
                        ->maxLength(100)
                        ->columnSpan(1),

                    Forms\Components\Select::make('channel')
                        ->label(__('communication::resource.channel'))
                        ->options(collect(NotificationChannel::cases())->mapWithKeys(
                            fn (NotificationChannel $c) => [$c->value => $c->label()]
                        ))
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Select::make('audience')
                        ->label(__('communication::resource.audience'))
                        ->options(collect(NotificationAudience::cases())->mapWithKeys(
                            fn (NotificationAudience $a) => [$a->value => $a->label()]
                        ))
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('communication::resource.is_active'))
                        ->default(true)
                        ->columnSpan(1),
                ])
                ->columns(2),

            Forms\Components\Section::make('Content')
                ->schema([
                    Forms\Components\Textarea::make('subject')
                        ->label(__('communication::resource.subject'))
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('body')
                        ->label(__('communication::resource.body'))
                        ->rows(5)
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\KeyValue::make('variables')
                        ->label(__('communication::resource.variables'))
                        ->keyLabel('Variable')
                        ->valueLabel('Description')
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_key')
                    ->label(__('communication::resource.event_key'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('channel')
                    ->label(__('communication::resource.channel'))
                    ->badge()
                    ->color(fn (NotificationChannel $state): string => match ($state) {
                        NotificationChannel::Push => 'info',
                        NotificationChannel::Sms => 'warning',
                        NotificationChannel::Whatsapp => 'success',
                        NotificationChannel::Email => 'primary',
                        NotificationChannel::InApp => 'gray',
                    })
                    ->formatStateUsing(fn (NotificationChannel $state) => $state->label()),

                Tables\Columns\TextColumn::make('audience')
                    ->label(__('communication::resource.audience'))
                    ->badge()
                    ->color(fn (NotificationAudience $state): string => match ($state) {
                        NotificationAudience::Customer => 'success',
                        NotificationAudience::Vendor => 'warning',
                        NotificationAudience::Admin => 'danger',
                    })
                    ->formatStateUsing(fn (NotificationAudience $state) => $state->label()),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('communication::resource.is_active'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('event_key')
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->options(collect(NotificationChannel::cases())->mapWithKeys(
                        fn (NotificationChannel $c) => [$c->value => $c->label()]
                    )),
                Tables\Filters\SelectFilter::make('audience')
                    ->options(collect(NotificationAudience::cases())->mapWithKeys(
                        fn (NotificationAudience $a) => [$a->value => $a->label()]
                    )),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('communication::resource.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationTemplates::route('/'),
            'create' => Pages\CreateNotificationTemplate::route('/create'),
            'edit' => Pages\EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}
