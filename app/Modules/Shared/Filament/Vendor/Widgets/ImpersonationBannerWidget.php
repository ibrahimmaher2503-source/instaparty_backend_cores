<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Vendor\Widgets;

use App\Modules\Identity\Application\Actions\EndImpersonationAction;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class ImpersonationBannerWidget extends Widget
{
    protected static string $view = 'vendor-portal.widgets.impersonation-banner';

    protected static ?int $sort = -100;

    protected static bool $isLazy = false;

    public string $adminName = '';

    public string $startedAt = '';

    public bool $isImpersonating = false;

    public function mount(): void
    {
        $this->isImpersonating = session()->has('impersonator_id');

        if ($this->isImpersonating) {
            $this->adminName = session('impersonator_name', 'Admin');
            $startedAt = session('impersonation_started_at');
            $this->startedAt = $startedAt
                ? Carbon::parse($startedAt)->format('d M Y, H:i')
                : '';
        }
    }

    public function endImpersonation(): void
    {
        app(EndImpersonationAction::class)->execute();

        $this->redirect(filament()->getPanel('admin')->getUrl(), navigate: false);
    }

    public static function canView(): bool
    {
        return session()->has('impersonator_id');
    }
}
