<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

enum ServiceStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return __('catalog.status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingReview => 'warning',
            self::Published => 'success',
            self::Archived => 'danger',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::PendingReview,
            self::PendingReview => $next === self::Published || $next === self::Draft,
            self::Published => $next === self::Archived || $next === self::Draft,
            self::Archived => $next === self::Draft,
        };
    }
}
