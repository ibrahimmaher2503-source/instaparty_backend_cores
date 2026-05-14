<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Vendor\Pages;

use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class VendorNotificationPreferencesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'settings';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'vendor-portal.pages.vendor-notification-preferences';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.notifications.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.notifications.title');
    }

    public function mount(): void
    {
        $userId = auth()->id();
        $preferences = NotificationPreference::query()
            ->where('user_id', $userId)
            ->get()
            ->keyBy(fn (NotificationPreference $p) => $p->channel->value.'_'.$p->event_category->value);

        $formData = [];
        foreach (NotificationChannel::cases() as $channel) {
            foreach (EventCategory::cases() as $category) {
                if (! $category->canBeDisabled()) {
                    continue;
                }
                $key = $channel->value.'_'.$category->value;
                $pref = $preferences->get($key);
                $formData[$key] = $pref === null ? true : $pref->is_enabled;
            }
        }

        $this->form->fill($formData);
    }

    public function form(Form $form): Form
    {
        $schema = [];

        foreach (NotificationChannel::cases() as $channel) {
            $toggles = [];
            foreach (EventCategory::cases() as $category) {
                if (! $category->canBeDisabled()) {
                    $toggles[] = Toggle::make($channel->value.'_'.$category->value)
                        ->label($category->label())
                        ->disabled()
                        ->helperText(__('vendor-portal.notifications.system_locked'));

                    continue;
                }
                $toggles[] = Toggle::make($channel->value.'_'.$category->value)
                    ->label($category->label())
                    ->default(true);
            }

            $schema[] = Section::make($channel->label())
                ->schema([Grid::make(3)->schema($toggles)]);
        }

        return $form->schema($schema)->statePath('data');
    }

    public function save(): void
    {
        $userId = auth()->id();
        $data = $this->form->getState();

        foreach (NotificationChannel::cases() as $channel) {
            foreach (EventCategory::cases() as $category) {
                if (! $category->canBeDisabled()) {
                    continue;
                }
                $key = $channel->value.'_'.$category->value;
                NotificationPreference::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'channel' => $channel->value,
                        'event_category' => $category->value,
                    ],
                    [
                        'public_id' => (string) Str::ulid(),
                        'is_enabled' => (bool) ($data[$key] ?? true),
                    ]
                );
            }
        }

        Notification::make()->title(__('vendor-portal.notifications.saved'))->success()->send();
    }
}
