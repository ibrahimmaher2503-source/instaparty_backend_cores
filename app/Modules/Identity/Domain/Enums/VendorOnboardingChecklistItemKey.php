<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum VendorOnboardingChecklistItemKey: string
{
    case Profile          = 'profile';
    case Banking          = 'banking';
    case DocsUploaded     = 'docs_uploaded';
    case DocsApproved     = 'docs_approved';
    case Coverage         = 'coverage';
    case Hours            = 'hours';
    case ServiceDrafted   = 'service_drafted';
    case ServiceSubmitted = 'service_submitted';
    case ApprovalStatus   = 'approval_status';
    case ApprovedTypes    = 'approved_types';
}
