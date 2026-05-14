<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class VendorProfilePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'profile';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'vendor-portal.pages.vendor-profile';

    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.profile.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.profile.title');
    }

    public function mount(): void
    {
        $profile = $this->getVendorProfile();

        $this->form->fill([
            'business_name_en' => $profile->getTranslation('business_name', 'en'),
            'business_name_ar' => $profile->getTranslation('business_name', 'ar'),
            'bio_en' => $profile->getTranslation('bio', 'en'),
            'bio_ar' => $profile->getTranslation('bio', 'ar'),
            'address_line_en' => $profile->getTranslation('address_line', 'en'),
            'address_line_ar' => $profile->getTranslation('address_line', 'ar'),
            'primary_governorate_id' => $profile->primary_governorate_id,
            'primary_city_id' => $profile->primary_city_id,
            'bank_name' => $profile->bank_name,
            'bank_account_holder' => $profile->bank_account_holder,
            'bank_iban' => $profile->bank_iban,
            'bank_swift_bic' => $profile->bank_swift_bic,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('vendor-portal.profile.title'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('business_name_en')
                            ->label(__('vendor-portal.profile.business_name').' (EN)')
                            ->required()
                            ->maxLength(200),
                        TextInput::make('business_name_ar')
                            ->label(__('vendor-portal.profile.business_name').' (AR)')
                            ->required()
                            ->maxLength(200),
                        Textarea::make('bio_en')
                            ->label(__('vendor-portal.profile.bio').' (EN)')
                            ->rows(3)
                            ->columnSpan(1),
                        Textarea::make('bio_ar')
                            ->label(__('vendor-portal.profile.bio').' (AR)')
                            ->rows(3)
                            ->columnSpan(1),
                    ]),

                Section::make(__('vendor-portal.profile.address_line'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('address_line_en')
                            ->label(__('vendor-portal.profile.address_line').' (EN)')
                            ->maxLength(255),
                        TextInput::make('address_line_ar')
                            ->label(__('vendor-portal.profile.address_line').' (AR)')
                            ->maxLength(255),
                        Select::make('primary_governorate_id')
                            ->label('Governorate')
                            ->options(fn () => Governorate::query()->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('primary_city_id', null)),
                        Select::make('primary_city_id')
                            ->label('City')
                            ->options(
                                fn (callable $get) => $get('primary_governorate_id')
                                    ? City::query()
                                        ->where('governorate_id', $get('primary_governorate_id'))
                                        ->pluck('name', 'id')
                                    : []
                            )
                            ->required(),
                    ]),

                Section::make('Bank Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('bank_name')
                            ->label(__('vendor-portal.profile.bank_name'))
                            ->maxLength(100),
                        TextInput::make('bank_account_holder')
                            ->label(__('vendor-portal.profile.bank_holder_name'))
                            ->maxLength(150),
                        TextInput::make('bank_iban')
                            ->label(__('vendor-portal.profile.bank_iban'))
                            ->maxLength(34)
                            ->regex('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/')
                            ->validationMessages(['regex' => 'Must be a valid IBAN (e.g. EG12 1234 5678 …)'])
                            ->placeholder('EG...'),
                        TextInput::make('bank_swift')
                            ->label(__('vendor-portal.profile.bank_swift'))
                            ->maxLength(11),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $profile = $this->getVendorProfile();

        $payload = [
            'business_name' => ['en' => $data['business_name_en'], 'ar' => $data['business_name_ar']],
            'bio' => ['en' => $data['bio_en'] ?? '', 'ar' => $data['bio_ar'] ?? ''],
            'address_line' => ['en' => $data['address_line_en'] ?? '', 'ar' => $data['address_line_ar'] ?? ''],
            'primary_governorate_id' => $data['primary_governorate_id'],
            'primary_city_id' => $data['primary_city_id'],
            'bank_name' => $data['bank_name'],
            'bank_account_holder' => $data['bank_account_holder'],
            'bank_iban' => $data['bank_iban'],
            'bank_swift_bic' => $data['bank_swift'],
        ];

        app(UpdateVendorProfileAction::class)->execute($profile, $payload);

        Notification::make()
            ->title(__('vendor-portal.profile.saved'))
            ->success()
            ->send();
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
