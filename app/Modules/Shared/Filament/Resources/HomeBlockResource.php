<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources;

use App\Modules\Shared\Application\Actions\ReorderHomeBlocksAction;
use App\Modules\Shared\Application\Actions\SaveHomeBlockAction;
use App\Modules\Shared\Domain\Enums\HomeBlockType;
use App\Modules\Shared\Domain\Models\HomeBlock;
use App\Modules\Shared\Filament\Resources\HomeBlockResource\Pages;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HomeBlockResource extends Resource
{
    protected static ?string $model = HomeBlock::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.appearance');
    }

    public static function getModelLabel(): string
    {
        return 'Homepage Block';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Homepage Blocks';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Block')
                ->columns(3)
                ->schema([
                    Select::make('block_type')
                        ->options(
                            collect(HomeBlockType::cases())
                                ->mapWithKeys(fn (HomeBlockType $t) => [$t->value => $t->label()])
                                ->all(),
                        )
                        ->required()
                        ->live()
                        ->disabledOn('edit'),
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('position')->numeric()->default(0),
                    Toggle::make('is_visible')->default(true),
                ]),

            Section::make('Scheduling')
                ->collapsed()
                ->columns(2)
                ->schema([
                    DateTimePicker::make('starts_at')->seconds(false),
                    DateTimePicker::make('ends_at')->seconds(false),
                ]),

            Section::make('Payload')
                ->schema(fn ($get): array => static::payloadSchema(HomeBlockType::tryFrom((string) $get('block_type')))),
        ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private static function payloadSchema(?HomeBlockType $type): array
    {
        if ($type === null) {
            return [];
        }

        $bilingualText = fn (string $field, string $label, bool $required = true): array => [
            Tabs::make($field)
                ->tabs([
                    Tabs\Tab::make('EN')->schema([
                        TextInput::make("payload.{$field}.en")->label($label.' (EN)')
                            ->required($required)->maxLength(240),
                    ]),
                    Tabs\Tab::make('AR')->schema([
                        TextInput::make("payload.{$field}.ar")->label($label.' (AR)')
                            ->required($required)->maxLength(240),
                    ]),
                ]),
        ];

        return match ($type) {
            HomeBlockType::HeroCarousel => [
                Repeater::make('payload.slides')
                    ->minItems(1)->maxItems(10)->reorderable()->collapsible()
                    ->schema([
                        TextInput::make('image_url')->url()->required()->maxLength(1024),
                        TextInput::make('headline.en')->required()->maxLength(160),
                        TextInput::make('headline.ar')->required()->maxLength(160),
                        TextInput::make('sub.en')->maxLength(240),
                        TextInput::make('sub.ar')->maxLength(240),
                        TextInput::make('cta_label.en')->maxLength(60),
                        TextInput::make('cta_label.ar')->maxLength(60),
                        TextInput::make('cta_url')->maxLength(512),
                    ])->columns(2),
            ],

            HomeBlockType::FeaturedServices => array_merge(
                $bilingualText('title', 'Title'),
                [
                    Repeater::make('payload.service_public_ids')
                        ->simple(TextInput::make('value')->required()->maxLength(26)->minLength(26))
                        ->minItems(1)->maxItems(24)->reorderable(),
                ],
            ),

            HomeBlockType::FeaturedOccasions, HomeBlockType::FeaturedCategories => array_merge(
                $bilingualText('title', 'Title'),
                [
                    Repeater::make('payload.public_ids')
                        ->simple(TextInput::make('value')->required()->maxLength(26)->minLength(26))
                        ->minItems(1)->maxItems(24)->reorderable(),
                ],
            ),

            HomeBlockType::VendorSpotlight => array_merge(
                $bilingualText('title', 'Title'),
                [
                    TextInput::make('payload.vendor_public_id')->required()->maxLength(26)->minLength(26),
                ],
            ),

            HomeBlockType::CtaBanner => array_merge(
                $bilingualText('headline', 'Headline'),
                $bilingualText('cta_label', 'CTA label'),
                [
                    TextInput::make('payload.image_url')->url()->maxLength(1024),
                    TextInput::make('payload.cta_url')->required()->maxLength(512),
                ],
            ),

            HomeBlockType::TextImageSplit => array_merge(
                $bilingualText('headline', 'Headline'),
                [
                    Textarea::make('payload.body.en')->required()->maxLength(2000),
                    Textarea::make('payload.body.ar')->required()->maxLength(2000),
                    TextInput::make('payload.image_url')->required()->url()->maxLength(1024),
                    Select::make('payload.image_side')->options(['left' => 'Left', 'right' => 'Right'])->required(),
                ],
            ),

            HomeBlockType::Testimonials => array_merge(
                $bilingualText('title', 'Title'),
                [
                    Repeater::make('payload.items')
                        ->minItems(1)->maxItems(12)->collapsible()
                        ->schema([
                            Textarea::make('quote.en')->required()->maxLength(600),
                            Textarea::make('quote.ar')->required()->maxLength(600),
                            TextInput::make('author')->required()->maxLength(120),
                            TextInput::make('avatar_url')->url()->maxLength(1024),
                        ])->columns(2),
                ],
            ),

            HomeBlockType::LoyaltyPromo => array_merge(
                $bilingualText('headline', 'Headline'),
                [
                    Textarea::make('payload.body.en')->maxLength(600),
                    Textarea::make('payload.body.ar')->maxLength(600),
                    TextInput::make('payload.cta_url')->maxLength(512),
                ],
            ),
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('block_type')
                    ->badge()
                    ->formatStateUsing(fn (HomeBlockType $state): string => $state->label()),
                IconColumn::make('is_visible')->boolean(),
                TextColumn::make('starts_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ends_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('block_type')
                    ->options(collect(HomeBlockType::cases())->mapWithKeys(fn (HomeBlockType $t) => [$t->value => $t->label()])->all()),
                TernaryFilter::make('is_visible'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomeBlocks::route('/'),
            'create' => Pages\CreateHomeBlock::route('/create'),
            'edit' => Pages\EditHomeBlock::route('/{record}/edit'),
        ];
    }

    public static function persistViaAction(array $data, ?HomeBlock $record = null): HomeBlock
    {
        return app(SaveHomeBlockAction::class)->execute($record, $data);
    }

    public static function reorderViaAction(array $orderedPublicIds): void
    {
        app(ReorderHomeBlocksAction::class)->execute($orderedPublicIds);
    }
}
