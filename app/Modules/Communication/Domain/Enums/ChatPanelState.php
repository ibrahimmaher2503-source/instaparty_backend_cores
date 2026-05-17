<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum ChatPanelState: string
{
    /** Booking has no chat thread yet (pre-submission or wrong state). */
    case Placeholder = 'placeholder';

    /** Thread exists and is in the review window — vendor may send. */
    case Open = 'open';

    /** Admin has frozen the thread via FreezeChatAction. */
    case Frozen = 'frozen';

    /** Booking confirmed/completed/cancelled — chat window has passed. */
    case Closed = 'closed';

    /** Thread locked by lifecycle, not by admin (e.g. vendor accepted). */
    case SystemLocked = 'system_locked';
}
