<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Application\Actions\UpdateVendorCoverageAreasAction;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorCoverageAreasPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'profile';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'vendor-portal.pages.vendor-coverage-areas';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.coverage_areas.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.coverage_areas.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VendorCoverageArea::query()
                    ->where('vendor_profile_id', $this->getVendorProfile()->id)
                    ->with('city')
            )
            ->columns([
                TextColumn::make('city.name')
                    ->label(__('vendor-portal.coverage_areas.city'))
                    ->searchable(),
                TextColumn::make('delivery_fee_minor')
                    ->label(__('vendor-portal.coverage_areas.delivery_fee'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('min_order_minor')
                    ->label(__('vendor-portal.coverage_areas.min_order'))
                    ->money('EGP', divideBy: 100),
            ])
            ->actions([
                TableAction::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VendorCoverageArea $record): void {
                        $profile = $this->getVendorProfile();
                        $remaining = VendorCoverageArea::query()
                            ->where('vendor_profile_id', $profile->id)
                            ->where('id', '!=', $record->id)
                            ->get()
                            ->map(fn (VendorCoverageArea $a) => [
                                'city_id' => $a->city_id,
                                'delivery_fee_minor' => $a->delivery_fee_minor,
                                'min_order_minor' => $a->min_order_minor,
                            ])
                            ->toArray();

                        app(UpdateVendorCoverageAreasAction::class)->execute($profile, $remaining);
                        Notification::make()->title(__('vendor-portal.coverage_areas.removed'))->success()->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addArea')
                ->label(__('vendor-portal.coverage_areas.add_area'))
                ->icon('heroicon-o-plus')
                ->form([
                    Select::make('city_id')
                        ->label(__('vendor-portal.coverage_areas.city'))
                        ->options(fn () => City::query()->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Grid::make(2)->schema([
                        TextInput::make('delivery_fee')
                            ->label(__('vendor-portal.coverage_areas.delivery_fee').' (EGP)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('min_order')
                            ->label(__('vendor-portal.coverage_areas.min_order').' (EGP)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),
                ])
                ->action(function (array $data): void {
                    $profile = $this->getVendorProfile();

                    $existing = VendorCoverageArea::query()
                        ->where('vendor_profile_id', $profile->id)
                        ->get()
                        ->map(fn (VendorCoverageArea $a) => [
                            'city_id' => $a->city_id,
                            'delivery_fee_minor' => $a->delivery_fee_minor,
                            'min_order_minor' => $a->min_order_minor,
                        ])
                        ->toArray();

                    $existing[] = [
                        'city_id' => (int) $data['city_id'],
                        'delivery_fee_minor' => (int) round((float) $data['delivery_fee'] * 100),
                        'min_order_minor' => (int) round((float) $data['min_order'] * 100),
                    ];

                    app(UpdateVendorCoverageAreasAction::class)->execute($profile, $existing);
                    Notification::make()->title(__('vendor-portal.coverage_areas.saved'))->success()->send();
                }),
        ];
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
