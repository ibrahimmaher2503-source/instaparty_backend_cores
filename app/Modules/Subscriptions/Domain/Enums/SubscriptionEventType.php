<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Enums;

enum SubscriptionEventType: string
{
    case Created               = 'subscription.created';
    case Activated             = 'subscription.activated';
    case Upgraded              = 'subscription.upgraded';
    case Downgraded            = 'subscription.downgraded';
    case CancelRequested       = 'subscription.cancel_requested';
    case Cancelled             = 'subscription.cancelled';
    case RenewalAttemptFailed  = 'subscription.renewal_attempt_failed';
    case PastDue               = 'subscription.past_due';
    case Renewed               = 'subscription.renewed';
    case Expired               = 'subscription.expired';
    case Superseded            = 'subscription.superseded';
    case TierChanged           = 'subscription.tier_changed';
    case AdminOverrideApplied  = 'subscription.admin_override_applied';
    case AdminOverrideEnded    = 'subscription.admin_override_ended';
}
