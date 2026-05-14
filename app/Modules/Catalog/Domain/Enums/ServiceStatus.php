<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

enum ServiceStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case ChangesRequested = 'changes_requested';
    case Published = 'published';
    case Rejected = 'rejected';
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
            self::ChangesRequested => 'warning',
            self::Published => 'success',
            self::Rejected => 'danger',
            self::Archived => 'danger',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::PendingReview,
            self::PendingReview => $next === self::Published
                || $next === self::Rejected
                || $next === self::ChangesRequested
                || $next === self::Draft,
            self::ChangesRequested => $next === self::PendingReview || $next === self::Draft,
            self::Published => $next === self::PendingReview || $next === self::Archived || $next === self::Draft,
            self::Rejected => $next === self::Archived,
            self::Archived => $next === self::Draft,
        };
    }

    public function isActionable(): bool
    {
        return $this === self::PendingReview;
    }
}
