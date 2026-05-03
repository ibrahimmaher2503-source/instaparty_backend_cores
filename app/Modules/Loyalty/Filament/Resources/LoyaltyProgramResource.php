<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyProgramResource extends Resource
{
    use Translatable;

    protected static ?string $model = LoyaltyProgram::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Vendors';

    protected static ?string $navigationLabel = 'Loyalty Programs';

    protected static ?int $navigationSort = 40;

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        // Scope to own vendor profile if the authenticated user is a vendor (not admin)
        if (auth()->check() && !auth()->user()->hasRole('super_admin')) {
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
                    Forms\Components\Tabs\Tab::make('العربية')
                        ->schema([
                            Forms\Components\TextInput::make('name.ar')
                                ->label('الاسم (عربي)')
                                ->required()
                                ->maxLength(150),
                            Forms\Components\Textarea::make('terms.ar')
                                ->label('الشروط (عربي)')
                                ->rows(3)
                                ->maxLength(2000),
                        ]),
                ])
                ->columnSpanFull(),

            Forms\Components\Section::make('Program Settings')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            'active'   => 'Active',
                            'paused'   => 'Paused',
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
                    ->color(fn (string $state): string => match ($state) {
                        'active'   => 'success',
                        'paused'   => 'warning',
                        'archived' => 'gray',
                        default    => 'gray',
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
                        'active'   => 'Active',
                        'paused'   => 'Paused',
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
            'index' => \App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages\ListLoyaltyPrograms::route('/'),
            'create' => \App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages\CreateLoyaltyProgram::route('/create'),
            'edit' => \App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages\EditLoyaltyProgram::route('/{record}/edit'),
        ];
    }
}
