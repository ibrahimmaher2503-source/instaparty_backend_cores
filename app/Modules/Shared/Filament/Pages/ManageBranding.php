<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Pages;

use App\Modules\Shared\Application\Actions\SaveBrandingAction;
use App\Modules\Shared\Domain\Models\BrandingSetting;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageBranding extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.manage-branding';

    public ?array $data = [];

    public ?BrandingSetting $record = null;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.appearance');
    }

    public static function getNavigationLabel(): string
    {
        return 'Branding';
    }

    public function getTitle(): string
    {
        return 'Branding';
    }

    public function mount(): void
    {
        $this->record = BrandingSetting::current();

        $this->form->fill([
            'site_name' => $this->record->site_name,
            'tagline' => $this->record->tagline,
            'support_email' => $this->record->support_email,
            'support_phone' => $this->record->support_phone,
            'whatsapp_number' => $this->record->whatsapp_number,
            'social' => (array) ($this->record->social ?? []),
            'address_line' => $this->record->address_line,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record)
            ->statePath('data')
            ->schema([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        Tabs::make('Translations')
                            ->columnSpanFull()
                            ->tabs([
                                Tabs\Tab::make('English')->schema([
                                    TextInput::make('site_name.en')->label('Site name (EN)')->required()->maxLength(120),
                                    TextInput::make('tagline.en')->label('Tagline (EN)')->maxLength(255),
                                    TextInput::make('address_line.en')->label('Address (EN)')->maxLength(255),
                                ]),
                                Tabs\Tab::make('العربية')->schema([
                                    TextInput::make('site_name.ar')->label('اسم الموقع')->required()->maxLength(120),
                                    TextInput::make('tagline.ar')->label('الشعار')->maxLength(255),
                                    TextInput::make('address_line.ar')->label('العنوان')->maxLength(255),
                                ]),
                            ]),
                    ]),

                Section::make('Contact')
                    ->columns(3)
                    ->schema([
                        TextInput::make('support_email')->email()->maxLength(191),
                        TextInput::make('support_phone')->tel()->maxLength(32),
                        TextInput::make('whatsapp_number')->tel()->maxLength(32),
                    ]),

                Section::make('Social')
                    ->columns(2)
                    ->schema([
                        TextInput::make('social.instagram')->label('Instagram')->url()->prefix('https://'),
                        TextInput::make('social.facebook')->label('Facebook')->url()->prefix('https://'),
                        TextInput::make('social.tiktok')->label('TikTok')->url()->prefix('https://'),
                        TextInput::make('social.x')->label('X (Twitter)')->url()->prefix('https://'),
                        TextInput::make('social.youtube')->label('YouTube')->url()->prefix('https://'),
                    ]),

                Section::make('Assets')
                    ->columns(2)
                    ->schema([
                        Group::make([
                            SpatieMediaLibraryFileUpload::make('logo_light')
                                ->collection('logo_light')->image()->label('Logo (light)'),
                            SpatieMediaLibraryFileUpload::make('logo_dark')
                                ->collection('logo_dark')->image()->label('Logo (dark)'),
                            SpatieMediaLibraryFileUpload::make('favicon')
                                ->collection('favicon')->image()->label('Favicon'),
                        ]),
                        Group::make([
                            SpatieMediaLibraryFileUpload::make('og_image')
                                ->collection('og_image')->image()->label('OG image (1200x630)'),
                            SpatieMediaLibraryFileUpload::make('app_store_badge')
                                ->collection('app_store_badge')->image()->label('App Store badge'),
                            SpatieMediaLibraryFileUpload::make('play_store_badge')
                                ->collection('play_store_badge')->image()->label('Play Store badge'),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        app(SaveBrandingAction::class)->execute($data);

        Notification::make()
            ->title('Branding saved')
            ->success()
            ->send();
    }
}
