<?php

declare(strict_types=1);

namespace App\Modules\Booking\Console\Commands;

use Illuminate\Console\Command;

class ExpireVendorProposalsCommand extends Command
{
    protected $signature = 'booking:expire-vendor-proposals';

    protected $description = 'Expire vendor proposal consents that have passed their consent_expires_at deadline';

    public function handle(): int
    {
        // TODO: implement in T050 — ExpireVendorProposalsAction
        $this->info('ExpireVendorProposalsCommand: not yet implemented.');

        return Command::SUCCESS;
    }
}
