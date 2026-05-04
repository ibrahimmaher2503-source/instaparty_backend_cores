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

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.loyalty');
    }

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

        // Scope to own vendor profile if the authenticated user is a vendor (not admin)
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
            Forms\Components\Tabs::make('Translations')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('English')
                        ->schema([
                            Forms\Components\TextInput::make('name.en')
                                ->label('Name (English)')
                                ->required()
                                ->maxLength(150),
                            Forms\Components\Textarea::make('terms.en')
                                ->label('Terms (English)')
                                ->rows(3)
                                ->maxLength(2000),
                        ]),
                    Forms\Components\Tabs\Tab::make('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©')
                        ->schema([
                            Forms\Components\TextInput::make('name.ar')
                                ->label('Ø§Ù„Ø§Ø³Ù… (Ø¹Ø±Ø¨ÙŠ)')
                                ->required()
                                ->maxLength(150),
                            Forms\Components\Textarea::make('terms.ar')
                                ->label('Ø§Ù„Ø´Ø±ÙˆØ· (Ø¹Ø±Ø¨ÙŠ)')
                                ->rows(3)
                                ->maxLength(2000),
                        ]),
                ])
                ->columnSpanFull(),

            Forms\Components\Section::make('Program Settings')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'paused' => 'Paused',
                            'archived' => 'Archived',
                        ])
                        ->default('active')
                        ->required(),
                    Forms\Components\TextInput::make('expiration_days')
                        ->label('Points Expiration (days)')
                        ->numeric()
                        ->nullable()
                        ->minValue(1)
                        ->maxValue(3650)
                        ->helperText('Leave empty for points that never expire.'),
                    Forms\Components\TextInput::make('currency')
                        ->default('EGP')
                        ->maxLength(3)
                        ->required(),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('public_id')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['en'] ?? '') : $state),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(function ($state): string {
                        $value = $state instanceof \BackedEnum ? $state->value : (string) $state;

                        return match ($value) {
                            'active' => 'success',
                            'paused' => 'warning',
                            'archived' => 'gray',
                            default => 'gray',
                        };
                    }),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('expiration_days')
                    ->label('Expires (days)')
                    ->default('Never'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
