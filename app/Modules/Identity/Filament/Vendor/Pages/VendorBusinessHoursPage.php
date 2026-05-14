<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Identity\Application\Actions\UpsertVendorBusinessHoursAction;
use App\Modules\Identity\Domain\Enums\DayOfWeek;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class VendorBusinessHoursPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'profile';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'vendor-portal.pages.vendor-business-hours';

    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.business_hours.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.business_hours.title');
    }

    public function mount(): void
    {
        $profile = $this->getVendorProfile();
        $existingHours = $profile->businessHours->keyBy(fn ($h) => $h->day_of_week->value);

        $formData = [];
        foreach (DayOfWeek::cases() as $day) {
            $existing = $existingHours->get($day->value);
            $isOpen = $existing && $existing->opens_at !== null;
            $formData["day_{$day->value}_open"] = $isOpen;
            $formData["day_{$day->value}_opens_at"] = $isOpen ? $existing->opens_at : '09:00';
            $formData["day_{$day->value}_closes_at"] = $isOpen ? $existing->closes_at : '22:00';
        }

        $this->form->fill($formData);
    }

    public function form(Form $form): Form
    {
        $schema = [];
        foreach (DayOfWeek::cases() as $day) {
            $schema[] = Section::make($day->label())
                ->compact()
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make("day_{$day->value}_open")
                            ->label(__('vendor-portal.business_hours.open'))
                            ->live()
                            ->default($day !== DayOfWeek::Friday),
                        TimePicker::make("day_{$day->value}_opens_at")
                            ->label(__('vendor-portal.business_hours.opens_at'))
                            ->seconds(false)
                            ->visible(fn (callable $get) => (bool) $get("day_{$day->value}_open"))
                            ->default('09:00'),
                        TimePicker::make("day_{$day->value}_closes_at")
                            ->label(__('vendor-portal.business_hours.closes_at'))
                            ->seconds(false)
                            ->visible(fn (callable $get) => (bool) $get("day_{$day->value}_open"))
                            ->default('22:00'),
                    ]),
                ]);
        }

        return $form->schema($schema)->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $profile = $this->getVendorProfile();

        $hours = [];
        foreach (DayOfWeek::cases() as $day) {
            $isOpen = (bool) ($data["day_{$day->value}_open"] ?? false);
            $hours[] = [
                'day_of_week' => $day->value,
                'opens_at' => $isOpen ? ($data["day_{$day->value}_opens_at"] ?? null) : null,
                'closes_at' => $isOpen ? ($data["day_{$day->value}_closes_at"] ?? null) : null,
            ];
        }

        app(UpsertVendorBusinessHoursAction::class)->execute($profile, $hours);

        Notification::make()->title(__('vendor-portal.business_hours.saved'))->success()->send();
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
