<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource\RelationManagers;

use App\Modules\Subscriptions\Application\Actions\InvalidatePlanFeaturesCache;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PlanFeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'features';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('feature_key')
                    ->required()
                    ->maxLength(60)
                    ->unique(ignoreRecord: true),

                Forms\Components\Select::make('value_type')
                    ->options([
                        'int' => 'Integer',
                        'bool' => 'Boolean',
                        'string' => 'String',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('value_int')
                    ->label('Value (Integer)')
                    ->numeric()
                    ->visible(fn ($get) => $get('value_type') === 'int'),

                Forms\Components\Toggle::make('value_bool')
                    ->label('Value (Boolean)')
                    ->visible(fn ($get) => $get('value_type') === 'bool'),

                Forms\Components\TextInput::make('value_string')
                    ->label('Value (String)')
                    ->maxLength(255)
                    ->visible(fn ($get) => $get('value_type') === 'string'),

                Forms\Components\TextInput::make('label')
                    ->label('Label (English)')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('feature_key')
            ->columns([
                Tables\Columns\TextColumn::make('feature_key')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('value_type')
                    ->badge(),

                Tables\Columns\TextColumn::make('value_int')
                    ->label('Value')
                    ->formatStateUsing(fn ($record) => match ($record->value_type) {
                        'int' => $record->value_int,
                        'bool' => $record->value_bool ? 'Yes' : 'No',
                        'string' => $record->value_string,
                        default => '—',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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

    public function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);
        app(InvalidatePlanFeaturesCache::class)->execute($this->getOwnerRecord()->id);

        return $record;
    }

    public function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);
        app(InvalidatePlanFeaturesCache::class)->execute($this->getOwnerRecord()->id);

        return $record;
    }

    public function handleRecordDelete(Model $record): void
    {
        parent::handleRecordDelete($record);
        app(InvalidatePlanFeaturesCache::class)->execute($this->getOwnerRecord()->id);
    }
}
