<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources;

use App\Modules\Shared\Domain\Models\FeatureFlag;
use App\Modules\Shared\Filament\Resources\FeatureFlagResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FeatureFlagResource extends Resource
{
    protected static ?string $model = FeatureFlag::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 60;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return 'Feature Flag';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Flag')->columns(2)->schema([
                TextInput::make('key')->disabled()->dehydrated(false)
                    ->helperText('Read-only. Flags prefixed with frontend.* are exposed publicly.'),
                Toggle::make('is_enabled'),
                TextInput::make('rollout_pct')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                Textarea::make('description')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->badge()->color('info')->searchable()->sortable(),
                IconColumn::make('is_enabled')->boolean()->label('Enabled'),
                TextColumn::make('rollout_pct')->suffix('%'),
                TextColumn::make('description')->limit(60)->placeholder('—'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('key')
            ->filters([
                TernaryFilter::make('is_enabled'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeatureFlags::route('/'),
            'edit' => Pages\EditFeatureFlag::route('/{record}/edit'),
        ];
    }
}
