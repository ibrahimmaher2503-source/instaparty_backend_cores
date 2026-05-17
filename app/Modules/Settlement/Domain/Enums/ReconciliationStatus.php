<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

enum ReconciliationStatus: string
{
    case Queued              = 'queued';
    case Running             = 'running';
    case Clean               = 'clean';
    case AnomaliesDetected   = 'anomalies_detected';
    case Repaired            = 'repaired';
    case RequiresManualReview = 'requires_manual_review';
    case Failed              = 'failed';
}
