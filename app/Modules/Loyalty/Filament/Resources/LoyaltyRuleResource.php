<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Domain\Enums\RuleKind;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use App\Modules\Loyalty\Filament\Resources\LoyaltyRuleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyRuleResource extends Resource
{
    use Translatable;

    protected static ?string $model = LoyaltyRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static ?string $navigationGroup = 'Loyalty';

    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.rules');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.rule.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.rule.plural');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('loyalty.sections.rule'))
                ->schema([
                    Forms\Components\Select::make('loyalty_program_id')
                        ->label(__('loyalty.fields.program'))
                        ->relationship('program', 'id')
                        ->getOptionLabelFromRecordUsing(function (LoyaltyProgram $record): string {
                            $name = $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true);

                            return sprintf('#%d — %s', $record->vendor_profile_id, $name ?: $record->public_id);
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('label')
                        ->label(__('loyalty.fields.label'))
                        ->maxLength(255),

                    Forms\Components\Select::make('rule_kind')
                        ->label(__('loyalty.fields.rule_kind'))
                        ->options(collect(RuleKind::cases())
                            ->mapWithKeys(fn (RuleKind $k) => [$k->value => $k->label()])
                            ->all())
                        ->required(),

                    Forms\Components\TextInput::make('multiplier')
                        ->label(__('loyalty.fields.multiplier'))
                        ->numeric()
                        ->step(0.01)
                        ->default(1.00)
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(99.99),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('loyalty.fields.is_active'))
                        ->default(true),

                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label(__('loyalty.fields.starts_at'))
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label(__('loyalty.fields.ends_at'))
                        ->nullable()
                        ->afterOrEqual('starts_at'),

                    Forms\Components\KeyValue::make('conditions')
                        ->label(__('loyalty.fields.conditions'))
                        ->keyLabel(__('loyalty.condition_key'))
                        ->valueLabel(__('loyalty.condition_value'))
                        ->nullable()
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['program']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('public_id')
                    ->label(__('loyalty.columns.public_id'))
                    ->copyable()
                    ->searchable()
                    ->limit(10),
                Tables\Columns\TextColumn::make('program.name')
                    ->label(__('loyalty.columns.program'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—')),
                Tables\Columns\TextColumn::make('label')
                    ->label(__('loyalty.columns.label'))
                    ->searchable()
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state[app()->getLocale()] ?? $state['en'] ?? '')
                        : (string) ($state ?? '')),
                Tables\Columns\TextColumn::make('rule_kind')
                    ->label(__('loyalty.columns.rule_kind'))
                    ->badge()
                    ->color(fn (RuleKind $state): string => match ($state) {
                        RuleKind::FirstBooking => 'success',
                        RuleKind::CategoryBonus => 'warning',
                        RuleKind::ThresholdBonus => 'info',
                        RuleKind::Referral => 'primary',
                    })
                    ->formatStateUsing(fn (RuleKind $state) => $state->label()),
                Tables\Columns\TextColumn::make('multiplier')
                    ->label(__('loyalty.columns.multiplier'))
                    ->numeric(decimalPlaces: 2)
                    ->suffix('×'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('loyalty.columns.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('loyalty.columns.starts_at'))
                    ->dateTime()
                    ->default(__('loyalty.no_start')),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('loyalty.columns.ends_at'))
                    ->dateTime()
                    ->default(__('loyalty.no_end')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('rule_kind')
                    ->label(__('loyalty.columns.rule_kind'))
                    ->options(collect(RuleKind::cases())
                        ->mapWithKeys(fn (RuleKind $k) => [$k->value => $k->label()])
                        ->all()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('loyalty.columns.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyRules::route('/'),
        ];
    }
}
