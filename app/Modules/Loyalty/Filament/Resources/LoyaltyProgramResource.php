<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages\CreateLoyaltyProgram;
use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages\EditLoyaltyProgram;
use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages\ListLoyaltyPrograms;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyProgramResource extends Resource
{
    use Translatable;

    protected static ?string $model = LoyaltyProgram::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Loyalty';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.programs');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.program.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.program.plural');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check() && ! auth()->user()->hasRole('super_admin')) {
            $vendorProfileId = auth()->user()->vendorProfile?->id;
            if ($vendorProfileId) {
                $query->where('vendor_profile_id', $vendorProfileId);
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('loyalty.sections.program'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('loyalty.fields.name'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('loyalty.fields.is_active'))
                        ->default(true)
                        ->onColor('success')
                        ->offColor('danger'),

                    Forms\Components\Textarea::make('terms')
                        ->label(__('loyalty.fields.terms'))
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('points_per_currency_unit')
                        ->label(__('loyalty.fields.points_per_currency_unit'))
                        ->numeric()
                        ->step(0.0001)
                        ->required()
                        ->default(1)
                        ->helperText(__('loyalty.help.points_per_currency_unit')),

                    Forms\Components\TextInput::make('points_value_minor')
                        ->label(__('loyalty.fields.points_value_minor'))
                        ->numeric()
                        ->required()
                        ->default(1)
                        ->prefix(fn (Forms\Get $get) => $get('points_value_currency') ?? 'EGP')
                        ->helperText(__('loyalty.help.points_value_minor')),

                    Forms\Components\TextInput::make('points_value_currency')
                        ->label(__('loyalty.fields.points_value_currency'))
                        ->default('EGP')
                        ->maxLength(3)
                        ->required(),

                    Forms\Components\TextInput::make('min_points_to_redeem')
                        ->label(__('loyalty.fields.min_points_to_redeem'))
                        ->numeric()
                        ->default(100)
                        ->required()
                        ->minValue(1),

                    Forms\Components\TextInput::make('max_redeem_pct')
                        ->label(__('loyalty.fields.max_redeem_pct'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(50)
                        ->required()
                        ->suffix('%'),

                    Forms\Components\TextInput::make('points_expire_after_days')
                        ->label(__('loyalty.fields.points_expire_after_days'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(3650)
                        ->placeholder(__('loyalty.never_expires'))
                        ->nullable(),
                ])
                ->columns(2),
        ]);
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
                Tables\Columns\TextColumn::make('vendor_profile_id')
                    ->label(__('loyalty.columns.vendor'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('loyalty.columns.name'))
                    ->searchable()
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state[app()->getLocale()] ?? $state['en'] ?? '')
                        : (string) $state),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('loyalty.columns.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('points_per_currency_unit')
                    ->label(__('loyalty.columns.points_per_currency_unit'))
                    ->numeric(decimalPlaces: 4),
                Tables\Columns\TextColumn::make('points_value_minor')
                    ->label(__('loyalty.columns.point_value'))
                    ->money('EGP', divideBy: 100),
                Tables\Columns\TextColumn::make('min_points_to_redeem')
                    ->label(__('loyalty.columns.min_points_to_redeem'))
                    ->numeric(),
                Tables\Columns\TextColumn::make('max_redeem_pct')
                    ->label(__('loyalty.columns.max_redeem_pct'))
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('points_expire_after_days')
                    ->label(__('loyalty.columns.points_expire_after_days'))
                    ->default(__('loyalty.never_expires')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('loyalty.columns.is_active')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyPrograms::route('/'),
            'create' => CreateLoyaltyProgram::route('/create'),
            'edit' => EditLoyaltyProgram::route('/{record}/edit'),
        ];
    }
}
