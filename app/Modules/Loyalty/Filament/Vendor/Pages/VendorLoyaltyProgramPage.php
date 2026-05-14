<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Vendor\Pages;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Application\Actions\ConfigureLoyaltyProgramAction;
use App\Modules\Loyalty\Application\DTOs\ProgramDraft;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class VendorLoyaltyProgramPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'engagement';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'vendor-portal.pages.vendor-loyalty-program';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.loyalty.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.loyalty.title');
    }

    public function mount(): void
    {
        $program = $this->getProgram();

        $this->form->fill([
            'name_en' => $program?->getTranslation('name', 'en') ?? '',
            'name_ar' => $program?->getTranslation('name', 'ar') ?? '',
            'is_active' => $program?->is_active ?? false,
            'points_per_currency_unit' => $program?->points_per_currency_unit ?? 1.0,
            'points_value_minor' => $program?->points_value_minor ?? 0,
            'min_points_to_redeem' => $program?->min_points_to_redeem ?? 100,
            'max_redeem_pct' => $program?->max_redeem_pct ?? 50,
            'points_expire_after_days' => $program?->points_expire_after_days,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('vendor-portal.loyalty.title'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name_en')
                            ->label('Program Name (EN)')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('name_ar')
                            ->label('Program Name (AR)')
                            ->required()
                            ->maxLength(100),
                    ]),
                    Toggle::make('is_active')
                        ->label(__('vendor-portal.loyalty.enabled')),
                ]),

            Section::make('Points Configuration')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('points_per_currency_unit')
                            ->label(__('vendor-portal.loyalty.points_per_egp'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01),
                        TextInput::make('points_value_minor')
                            ->label(__('vendor-portal.loyalty.points_value').' (Piastres)')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('min_points_to_redeem')
                            ->label(__('vendor-portal.loyalty.min_points_to_redeem'))
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        TextInput::make('max_redeem_pct')
                            ->label(__('vendor-portal.loyalty.max_redeem_pct').' (%)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(100),
                    ]),
                    TextInput::make('points_expire_after_days')
                        ->label(__('vendor-portal.loyalty.points_expire_after'))
                        ->numeric()
                        ->minValue(1)
                        ->hint('Leave empty for no expiry.'),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $vendor = $this->getVendorProfile();

        $draft = new ProgramDraft(
            name: ['en' => $data['name_en'], 'ar' => $data['name_ar']],
            terms: null,
            isActive: (bool) $data['is_active'],
            pointsPerCurrencyUnit: (float) $data['points_per_currency_unit'],
            pointsValueMinor: (int) $data['points_value_minor'],
            pointsValueCurrency: 'EGP',
            minPointsToRedeem: (int) $data['min_points_to_redeem'],
            maxRedeemPct: (int) $data['max_redeem_pct'],
            pointsExpireAfterDays: $data['points_expire_after_days'] ? (int) $data['points_expire_after_days'] : null,
        );

        app(ConfigureLoyaltyProgramAction::class)->execute($vendor->id, $draft);

        Notification::make()->title(__('vendor-portal.loyalty.saved'))->success()->send();
    }

    private function getProgram(): ?LoyaltyProgram
    {
        return LoyaltyProgram::query()
            ->where('vendor_profile_id', $this->getVendorProfile()->id)
            ->first();
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
