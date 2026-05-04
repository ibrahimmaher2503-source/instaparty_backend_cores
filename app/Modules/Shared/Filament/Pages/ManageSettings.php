<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Pages;

use App\Modules\Shared\Domain\Models\AppSetting;
use App\Modules\Shared\Domain\Models\FeatureFlag;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ManageSettings extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    protected static ?string $navigationLabel = 'App Settings';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.manage-settings';

    public array $settings = [];

    public function mount(): void
    {
        $this->settings = AppSetting::all()->pluck('value', 'key')->toArray();
    }

    public function form(Form $form): Form
    {
        $fields = AppSetting::all()->map(function (AppSetting $setting): TextInput {
            return TextInput::make("settings.{$setting->key}")
                ->label($setting->description ?? $setting->key)
                ->default($setting->value);
        })->all();

        return $form->schema([
            Section::make('Application Settings')
                ->schema($fields)
                ->columns(2),
        ])->statePath('settings');
    }

    public function saveSettings(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            AppSetting::where('key', $key)->update([
                'value' => $value,
                'updated_by' => Auth::id(),
            ]);
        }

        Notification::make()
            ->title('Settings saved successfully.')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FeatureFlag::query())
            ->heading('Feature Flags')
            ->columns([
                TextColumn::make('key')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_enabled')
                    ->boolean()
                    ->label('Enabled'),
                TextColumn::make('rollout_pct')
                    ->label('Rollout %')
                    ->suffix('%'),
                TextColumn::make('description')
                    ->limit(60)
                    ->placeholder('â€”'),
            ])
            ->actions([
                Action::make('toggle')
                    ->label(fn (FeatureFlag $record): string => $record->is_enabled ? 'Disable' : 'Enable')
                    ->icon(fn (FeatureFlag $record): string => $record->is_enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (FeatureFlag $record): string => $record->is_enabled ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (FeatureFlag $record): void {
                        $record->is_enabled = ! $record->is_enabled;
                        $record->save();

                        Notification::make()
                            ->title('Feature flag updated.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
