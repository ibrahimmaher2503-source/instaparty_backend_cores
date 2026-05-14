<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\RegisterVendorAction;
use App\Modules\Identity\Application\DTOs\RegisterVendorDTO;
use App\Modules\Identity\Domain\Enums\BusinessType;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Register;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

class RegisterVendorPage extends Register
{
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make()
                            ->schema([
                                $this->getNameFormComponent(),
                                $this->getEmailFormComponent(),
                                $this->getPhoneFormComponent(),
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                            ]),

                        Section::make(__('vendor-portal.profile.business_name'))
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('business_name_en')
                                        ->label('Business Name (English)')
                                        ->required()
                                        ->maxLength(200),
                                    TextInput::make('business_name_ar')
                                        ->label('اسم النشاط التجاري (عربي)')
                                        ->required()
                                        ->maxLength(200),
                                ]),
                                Grid::make(3)->schema([
                                    Select::make('business_type')
                                        ->label('Business Type')
                                        ->options([
                                            BusinessType::Individual->value => 'Individual',
                                            BusinessType::Company->value => 'Company',
                                            BusinessType::Establishment->value => 'Establishment',
                                        ])
                                        ->required(),
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
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone_e164')
            ->label('Phone')
            ->placeholder('+201234567890')
            ->required()
            ->maxLength(20)
            ->helperText(__('Include country code, e.g. +201234567890'));
    }

    // Override to skip hashing — RegisterVendorAction hashes internally.
    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::pages/auth/register.form.password.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->rule(Password::default())
            ->same('passwordConfirmation')
            ->validationAttribute(__('filament-panels::pages/auth/register.form.password.validation_attribute'));
    }

    protected function handleRegistration(array $data): Model
    {
        $dto = new RegisterVendorDTO(
            name: $data['name'],
            phoneE164: $data['phone_e164'],
            email: $data['email'],
            password: $data['password'],
            businessName: ['en' => $data['business_name_en'], 'ar' => $data['business_name_ar']],
            businessType: $data['business_type'],
            primaryGovernorateId: (int) $data['primary_governorate_id'],
            primaryCityId: (int) $data['primary_city_id'],
            preferredLocale: app()->getLocale(),
        );

        $vendorProfile = app(RegisterVendorAction::class)->execute($dto);

        return $vendorProfile->user;
    }
}
