<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum ChatFlagResolution: string
{
    case UpheldRedact = 'upheld_redact';
    case UpheldWarn = 'upheld_warn';
    case UpheldBlock = 'upheld_block';
    case DismissedFalsePositive = 'dismissed_false_positive';

    public function actionTaken(): ChatFlagAction
    {
        return match ($this) {
            self::UpheldRedact => ChatFlagAction::Redact,
            self::UpheldWarn => ChatFlagAction::Warn,
            self::UpheldBlock => ChatFlagAction::Block,
            self::DismissedFalsePositive => ChatFlagAction::None,
        };
    }
}
