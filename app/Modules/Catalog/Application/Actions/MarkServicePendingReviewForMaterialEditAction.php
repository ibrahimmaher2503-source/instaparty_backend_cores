<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\ServiceReturnedToReview;
use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @deprecated 035-service-edit-approval — for published services use SubmitServiceChangeRequestAction instead.
 *             This Action is kept for non-published flows (draft/pending_review).
 *             The `published` branch is now a no-op and returns the service unchanged.
 */
class MarkServicePendingReviewForMaterialEditAction
{
    /** @var list<string> */
    private const MATERIAL_FIELDS = [
        'base_price_minor',
        'base_price_currency',
        'category_id',
        'name',
        'short_description',
        'long_description',
    ];

    public function execute(Service $service, bool $coreMediaChanged = false): Service
    {
        // This action is now fully superseded by SubmitServiceChangeRequestAction (spec 035).
        // Published services route through SubmitServiceChangeRequestAction before reaching here.
        // Non-published services (draft/pending_review) were already a no-op in the original code.
        // Returning unchanged for all cases prevents the dark-window status flip.
        return $service;
    }
}
