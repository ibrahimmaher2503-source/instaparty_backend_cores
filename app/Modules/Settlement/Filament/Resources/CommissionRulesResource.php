<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Settlement\Domain\Models\CommissionRate;
use App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages\CreateCommissionRule;
use App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages\EditCommissionRule;
use App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages\ListCommissionRules;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommissionRulesResource extends Resource
{
    protected static ?string $model = CommissionRate::class;

    protected static ?string $navigationGroup = 'Settlement';

    protected static ?string $navigationIcon = 'heroicon-o-percent-badge';

    protected static ?string $navigationLabel = 'Commission Rules';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static ?string $slug = 'settlement-commission-rules';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Commission Rule')
                ->schema([
                    Select::make('category_id')
                        ->label('Category (null = any)')
                        ->relationship('category', 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'en'))
                        ->searchable()
                        ->preload()
                        ->placeholder('Any category (wildcard)')
                        ->nullable(),

                    Select::make('product_type')
                        ->label('Product Type (null = any)')
                        ->options([
                            ProductType::Rental->value => 'Rental',
                            ProductType::Sale->value => 'Sale',
                            ProductType::Digital->value => 'Digital',
                        ])
                        ->placeholder('Any type (wildcard)')
                        ->nullable(),

                    TextInput::make('commission_bps')
                        ->label('Commission (basis points)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(10000)
                        ->required()
                        ->helperText('100 bps = 1%,  1500 bps = 15%,  10000 bps = 100%'),

                    DatePicker::make('effective_from')
                        ->label('Effective From')
                        ->required()
                        ->default(today())
                        ->native(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('category.id')
                    ->label('Category')
                    ->formatStateUsing(fn ($record) => $record->category
                        ? $record->category->getTranslation('name', 'en')
                        : 'Any')
                    ->default('Any')
                    ->searchable(),

                Tables\Columns\TextColumn::make('product_type')
                    ->badge()
                    ->color(fn (?ProductType $state): string => $state ? match ($state) {
                        ProductType::Rental => 'warning',
                        ProductType::Sale => 'success',
                        ProductType::Digital => 'info',
                    } : 'gray')
                    ->formatStateUsing(fn (?ProductType $state): string => $state ? $state->label() : 'Any')
                    ->label('Product Type'),

                Tables\Columns\TextColumn::make('commission_bps')
                    ->label('Basis Points')
                    ->formatStateUsing(fn (int $state): string => $state.' bps ('.number_format($state / 100, 2).'%)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_from')
                    ->date()
                    ->label('Effective From')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Created At')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_type')
                    ->options([
                        ProductType::Rental->value => 'Rental',
                        ProductType::Sale->value => 'Sale',
                        ProductType::Digital->value => 'Digital',
                    ])
                    ->label('Product Type'),
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
            'index' => ListCommissionRules::route('/'),
            'create' => CreateCommissionRule::route('/create'),
            'edit' => EditCommissionRule::route('/{record}/edit'),
        ];
    }
}
