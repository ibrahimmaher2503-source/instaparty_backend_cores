<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Widgets;

use App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistDTO;
use App\Modules\Identity\Application\Services\VendorOnboardingChecklistService;
use Filament\Widgets\Widget;

class VendorOnboardingChecklistWidget extends Widget
{
    protected static string $view = 'identity::widgets.onboarding-checklist';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    protected function getViewData(): array
    {
        /** @var \App\Modules\Identity\Domain\Models\User $user */
        $user = auth()->user();

        $checklist = app(VendorOnboardingChecklistService::class)
            ->forVendor($user->vendorProfile);

        return ['checklist' => $checklist];
    }
}
