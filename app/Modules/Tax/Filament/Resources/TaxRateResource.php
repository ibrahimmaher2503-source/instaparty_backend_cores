<?php

declare(strict_types=1);

namespace App\Modules\Tax\Filament\Resources;

use App\Modules\Tax\Domain\Enums\TaxAppliesTo;
use App\Modules\Tax\Domain\Models\TaxRate;
use App\Modules\Tax\Filament\Resources\TaxRateResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxRateResource extends Resource
{
    use Translatable;

    protected static ?string $model = TaxRate::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.tax');
    }

    public static function getModelLabel(): string
    {
        return __('tax.rate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tax.rates');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Translations')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('English')
                        ->schema([
                            Forms\Components\TextInput::make('name.en')
                                ->label('Name (EN)')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Textarea::make('description.en')
                                ->label('Description (EN)')
                                ->rows(3),
                        ]),
                    Forms\Components\Tabs\Tab::make('العربية')
                        ->schema([
                            Forms\Components\TextInput::make('name.ar')
                                ->label('Name (AR)')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Textarea::make('description.ar')
                                ->label('Description (AR)')
                                ->rows(3),
                        ]),
                ])
                ->columnSpanFull(),

            Forms\Components\Section::make(__('tax.rate_settings'))
                ->schema([
                    Forms\Components\TextInput::make('rate_bps')
                        ->label(__('tax.rate_percent'))
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100_00)
                        ->suffix('%')
                        ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                        ->dehydrateStateUsing(fn (?float $state): ?int => $state !== null ? (int) round($state * 100) : null),

                    Forms\Components\Select::make('applies_to')
                        ->label(__('tax.applies_to'))
                        ->options(collect(TaxAppliesTo::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                        ->required(),

                    Forms\Components\CheckboxList::make('product_types')
                        ->label(__('tax.product_types'))
                        ->options([
                            'rental'  => 'Rental',
                            'sale'    => 'Sale',
                            'digital' => 'Digital',
                        ])
                        ->helperText(__('tax.product_types_hint')),

                    Forms\Components\Toggle::make('is_tax_inclusive')
                        ->label(__('tax.is_tax_inclusive')),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('tax.is_active'))
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('tax.effective_period'))
                ->schema([
                    Forms\Components\DatePicker::make('effective_from')
                        ->label(__('tax.effective_from'))
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('effective_to')
                        ->label(__('tax.effective_to'))
                        ->native(false)
                        ->afterOrEqual('effective_from'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('tax.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rate_bps')
                    ->label(__('tax.rate_percent'))
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2) . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('applies_to')
                    ->label(__('tax.applies_to'))
                    ->badge()
                    ->formatStateUsing(fn (TaxAppliesTo $state): string => $state->label()),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('tax.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('effective_from')
                    ->label(__('tax.effective_from'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('effective_to')
                    ->label(__('tax.effective_to'))
                    ->date()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('tax.is_active')),
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
            'index'  => Pages\ListTaxRates::route('/'),
            'create' => Pages\CreateTaxRate::route('/create'),
            'edit'   => Pages\EditTaxRate::route('/{record}/edit'),
        ];
    }
}
