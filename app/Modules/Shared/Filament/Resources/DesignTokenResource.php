<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources;

use App\Modules\Shared\Application\Actions\ActivateDesignTokenAction;
use App\Modules\Shared\Application\Actions\SaveDesignTokenAction;
use App\Modules\Shared\Domain\Models\DesignToken;
use App\Modules\Shared\Domain\Schemas\DesignTokenSchema;
use App\Modules\Shared\Filament\Resources\DesignTokenResource\Pages;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class DesignTokenResource extends Resource
{
    protected static ?string $model = DesignToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.appearance');
    }

    public static function getModelLabel(): string
    {
        return 'Design Token';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Design Tokens';
    }

    public static function form(Form $form): Form
    {
        $shades = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900'];

        $paletteSchema = function (string $palette) use ($shades): array {
            return array_map(
                fn (string $shade): ColorPicker => ColorPicker::make("tokens.colors.{$palette}.{$shade}")
                    ->label(ucfirst($palette).' '.$shade)
                    ->hex()
                    ->required(),
                $shades,
            );
        };

        return $form->schema([
            Section::make('Identity')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Name (slug)')
                        ->helperText('Internal identifier, e.g. "default", "ramadan", "summer-2026".')
                        ->required()
                        ->maxLength(64)
                        ->alphaDash(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->helperText('Activating this set swaps the live customer theme. Only one set can be active.')
                        ->dehydrated(false)
                        ->disabled(),
                ]),

            Tabs::make('Tokens')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('Colors')->schema([
                        Section::make('Primary')->columns(5)->schema($paletteSchema('primary')),
                        Section::make('Secondary')->columns(5)->schema($paletteSchema('secondary')),
                        Section::make('Accent')->columns(5)->schema($paletteSchema('accent')),
                        Section::make('Neutral')->columns(5)->schema($paletteSchema('neutral')),
                        Section::make('Semantic')->columns(4)->schema([
                            ColorPicker::make('tokens.colors.success')->hex()->required(),
                            ColorPicker::make('tokens.colors.warning')->hex()->required(),
                            ColorPicker::make('tokens.colors.danger')->hex()->required(),
                            ColorPicker::make('tokens.colors.info')->hex()->required(),
                        ]),
                    ]),

                    Tabs\Tab::make('Typography')->schema([
                        Grid::make(2)->schema([
                            Select::make('tokens.typography.fontFamilyBase')
                                ->label('Base font')
                                ->options(config('theme.fonts.base'))
                                ->required()
                                ->searchable(),
                            Select::make('tokens.typography.fontFamilyHeading')
                                ->label('Heading font')
                                ->options(config('theme.fonts.heading'))
                                ->required()
                                ->searchable(),
                            Select::make('tokens.typography.fontFamilyArabic')
                                ->label('Arabic font')
                                ->options(config('theme.fonts.arabic'))
                                ->required()
                                ->searchable(),
                            TextInput::make('tokens.typography.googleFontUrl')
                                ->label('Google Fonts URL')
                                ->helperText('Full <link href="..."> URL the frontend will load.')
                                ->url()
                                ->maxLength(1024),
                        ]),
                        KeyValue::make('tokens.typography.scale')
                            ->keyLabel('Step')->valueLabel('Size')
                            ->reorderable(false),
                        KeyValue::make('tokens.typography.weight')
                            ->keyLabel('Weight name')->valueLabel('Numeric')
                            ->reorderable(false),
                        KeyValue::make('tokens.typography.lineHeight')
                            ->keyLabel('Name')->valueLabel('Value')
                            ->reorderable(false),
                    ]),

                    Tabs\Tab::make('Spacing & Radius')->schema([
                        KeyValue::make('tokens.radius')
                            ->keyLabel('Token')->valueLabel('CSS value')
                            ->reorderable(false),
                        KeyValue::make('tokens.shadow')
                            ->keyLabel('Token')->valueLabel('CSS value')
                            ->reorderable(false),
                        Placeholder::make('spacing_help')
                            ->label('Spacing scale')
                            ->content('Spacing scale (px values, comma-separated) is set via API for now. UI editor for the array is Phase 2.'),
                    ]),

                    Tabs\Tab::make('Mode')->schema([
                        Toggle::make('tokens.mode.supportsDarkMode')->label('Supports dark mode'),
                        Select::make('tokens.mode.defaultMode')
                            ->options(['light' => 'Light', 'dark' => 'Dark'])
                            ->default('light')
                            ->required(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->badge()->searchable()->sortable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Activating swaps the live customer theme and clears the theme cache.')
                    ->visible(fn (DesignToken $record): bool => ! $record->is_active)
                    ->action(function (DesignToken $record): void {
                        app(ActivateDesignTokenAction::class)->execute($record);
                        Notification::make()
                            ->title('Theme activated')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (DesignToken $record): bool => ! $record->is_active),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDesignTokens::route('/'),
            'create' => Pages\CreateDesignToken::route('/create'),
            'edit' => Pages\EditDesignToken::route('/{record}/edit'),
        ];
    }

    public static function defaultTokens(): array
    {
        return DesignTokenSchema::default();
    }

    /**
     * Used by Create/Edit pages to delegate persistence to the Action.
     *
     * @param  array<string,mixed>  $data
     */
    public static function persistViaAction(array $data, ?DesignToken $record = null): DesignToken
    {
        try {
            return app(SaveDesignTokenAction::class)->execute(
                token: $record,
                name: (string) $data['name'],
                tokens: (array) ($data['tokens'] ?? []),
            );
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Invalid tokens')
                ->body(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();
            throw $e;
        }
    }
}
