<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use Filament\Pages\SimplePage;

class AccountSuspendedPage extends SimplePage
{
    protected static string $view = 'filament-panels::pages.auth.email-verification.prompt';

    protected static ?string $slug = 'account-suspended';

    protected static bool $shouldRegisterNavigation = false;

    public function getHeading(): string
    {
        return __('identity.account_suspended_title');
    }

    public function getSubheading(): ?string
    {
        return __('identity.account_suspended_body');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $profile = $user->vendorProfile;
        if (! $profile) {
            return false;
        }

        $status = $profile->approval_status;
        $statusValue = $status instanceof \BackedEnum ? $status->value : (string) $status;

        return $statusValue === 'suspended';
    }
}
