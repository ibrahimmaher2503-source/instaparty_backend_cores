<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources;

use App\Modules\Shared\Application\Actions\SaveNavigationMenuAction;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Enums\NavigationTargetType;
use App\Modules\Shared\Domain\Models\NavigationMenu;
use App\Modules\Shared\Domain\Models\NavigationMenuItem;
use App\Modules\Shared\Filament\Resources\NavigationMenuResource\Pages;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NavigationMenuResource extends Resource
{
    protected static ?string $model = NavigationMenu::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.appearance');
    }

    public static function getModelLabel(): string
    {
        return 'Navigation Menu';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Navigation Menus';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Menu')
                ->columns(2)
                ->schema([
                    Select::make('slot')
                        ->label('Slot')
                        ->options(
                            collect(NavigationSlot::cases())
                                ->mapWithKeys(fn (NavigationSlot $s) => [$s->value => $s->label()])
                                ->all(),
                        )
                        ->required()
                        ->disabledOn('edit'),
                    TextInput::make('name')
                        ->label('Internal name')
                        ->required()
                        ->maxLength(80),
                ]),

            Section::make('Items')
                ->schema([
                    Repeater::make('items')
                        ->relationship('rootItems')
                        ->orderColumn('position')
                        ->reorderable()
                        ->collapsible()
                        ->cloneable()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => static::itemPreviewLabel($state))
                        ->schema(static::itemSchema(allowChildren: true)),
                ]),
        ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private static function itemSchema(bool $allowChildren): array
    {
        $base = [
            Tabs::make('Label')
                ->tabs([
                    Tabs\Tab::make('English')->schema([
                        TextInput::make('label.en')->label('Label (EN)')->required()->maxLength(120),
                    ]),
                    Tabs\Tab::make('العربية')->schema([
                        TextInput::make('label.ar')->label('Label (AR)')->required()->maxLength(120),
                    ]),
                ]),
            Select::make('target_type')
                ->options(
                    collect(NavigationTargetType::cases())
                        ->mapWithKeys(fn (NavigationTargetType $t) => [$t->value => $t->label()])
                        ->all(),
                )
                ->required()
                ->live(),
            TextInput::make('target_value')
                ->label(fn ($get): string => match (NavigationTargetType::tryFrom((string) $get('target_type'))) {
                    NavigationTargetType::InternalPath => 'Path (e.g. /about)',
                    NavigationTargetType::ExternalUrl => 'External URL',
                    NavigationTargetType::CmsPage => 'CMS page slug',
                    NavigationTargetType::Category => 'Category public_id',
                    NavigationTargetType::Occasion => 'Occasion public_id',
                    default => 'Target',
                })
                ->required()
                ->maxLength(512),
            TextInput::make('icon')->label('Heroicon name')->maxLength(64),
            Toggle::make('is_visible')->default(true),
            Toggle::make('opens_in_new_tab')->default(false),
        ];

        if (! $allowChildren) {
            return $base;
        }

        $base[] = Repeater::make('children')
            ->relationship()
            ->orderColumn('position')
            ->reorderable()
            ->collapsible()
            ->defaultItems(0)
            ->itemLabel(fn (array $state): ?string => static::itemPreviewLabel($state))
            ->schema(static::itemSchema(allowChildren: false));

        return $base;
    }

    private static function itemPreviewLabel(array $state): ?string
    {
        $label = $state['label'] ?? null;
        if (is_array($label)) {
            return $label['en'] ?? $label['ar'] ?? null;
        }

        return $label ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slot')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof NavigationSlot ? $state->label() : (string) $state),
                TextColumn::make('name'),
                TextColumn::make('items_count')->counts('items')->label('Items'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNavigationMenus::route('/'),
            'create' => Pages\CreateNavigationMenu::route('/create'),
            'edit' => Pages\EditNavigationMenu::route('/{record}/edit'),
        ];
    }

    public static function persistViaAction(array $data): NavigationMenu
    {
        $slot = NavigationSlot::from((string) $data['slot']);

        return app(SaveNavigationMenuAction::class)->execute(
            slot: $slot,
            name: (string) $data['name'],
            items: static::normaliseItems((array) ($data['items'] ?? [])),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function normaliseItems(array $items): array
    {
        return array_map(
            static fn (array $item): array => [
                'label' => (array) ($item['label'] ?? []),
                'target_type' => (string) ($item['target_type'] ?? NavigationTargetType::InternalPath->value),
                'target_value' => (string) ($item['target_value'] ?? '/'),
                'icon' => $item['icon'] ?? null,
                'is_visible' => (bool) ($item['is_visible'] ?? true),
                'opens_in_new_tab' => (bool) ($item['opens_in_new_tab'] ?? false),
                'children' => isset($item['children']) && is_array($item['children'])
                    ? static::normaliseItems($item['children'])
                    : [],
            ],
            array_values($items),
        );
    }
}
