<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Listeners;

use App\Modules\Discovery\Domain\Events\ServiceSearchPerformed;
use App\Modules\Discovery\Domain\Models\SearchLog;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogSearchQueryListener implements ShouldQueue
{
    public function handle(ServiceSearchPerformed $event): void
    {
        SearchLog::create([
            'user_id'       => $event->userId,
            'query'         => $event->query ?? '',
            'locale'        => $event->locale,
            'filters'       => $event->filtersApplied,
            'results_count' => $event->resultsCount,
        ]);
    }
}
