<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources;

use App\Modules\Shared\Domain\Models\AppSetting;
use App\Modules\Shared\Filament\Resources\AppSettingResource\Pages;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AppSettingResource extends Resource
{
    protected static ?string $model = AppSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return 'App Setting';
    }

    public static function getPluralModelLabel(): string
    {
        return 'App Settings';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Setting')->columns(2)->schema([
                TextInput::make('key')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Internal identifier — read-only to prevent breaking consumers.'),
                Textarea::make('description')->rows(2)->columnSpanFull(),
                KeyValue::make('value')
                    ->keyLabel('Property')
                    ->valueLabel('Value')
                    ->reorderable(false)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->badge()->color('info')->searchable()->sortable(),
                TextColumn::make('description')->limit(60)->placeholder('—'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('key')
            ->actions([
                Tables\Actions\EditAction::make()->after(function ($record): void {
                    $record->updated_by = Auth::id();
                    $record->saveQuietly();
                }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppSettings::route('/'),
            'edit' => Pages\EditAppSetting::route('/{record}/edit'),
        ];
    }
}
