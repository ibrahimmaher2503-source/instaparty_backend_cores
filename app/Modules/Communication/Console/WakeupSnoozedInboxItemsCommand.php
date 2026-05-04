<?php

declare(strict_types=1);

namespace App\Modules\Communication\Console;

use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use Illuminate\Console\Command;

class WakeupSnoozedInboxItemsCommand extends Command
{
    protected $signature = 'inbox:wakeup-snoozed';

    protected $description = 'Reset expired snoozed admin inbox items back to unread.';

    public function handle(): int
    {
        $count = AdminInboxItem::query()
            ->where('status', AdminInboxStatus::Snoozed->value)
            ->where('snoozed_until', '<', now())
            ->update([
                'status' => AdminInboxStatus::Unread->value,
                'snoozed_until' => null,
                'updated_at' => now(),
            ]);

        $this->info("Woke up {$count} snoozed inbox item(s).");

        return self::SUCCESS;
    }
}
