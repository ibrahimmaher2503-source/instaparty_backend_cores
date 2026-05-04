<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Listeners;

use App\Modules\Reviews\Application\Services\RatingAggregationService;
use App\Modules\Reviews\Domain\Contracts\ServiceRatingWriter;
use App\Modules\Reviews\Domain\Contracts\VendorRatingWriter;
use App\Modules\Reviews\Domain\Events\ReviewApproved;
use App\Modules\Reviews\Domain\Events\ReviewHidden;
use App\Modules\Reviews\Domain\Events\ReviewRejected;
use App\Modules\Reviews\Domain\Events\ReviewSelfDeleted;
use App\Modules\Reviews\Domain\Events\ServiceRatingRecomputed;
use App\Modules\Reviews\Domain\Events\VendorRatingRecomputed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class RecomputeRatingOnApproval implements ShouldQueue
{
    public function __construct(
        private readonly RatingAggregationService $aggregationService,
        private readonly ServiceRatingWriter $serviceRatingWriter,
        private readonly VendorRatingWriter $vendorRatingWriter,
    ) {}

    public function handle(ReviewApproved|ReviewRejected|ReviewHidden|ReviewSelfDeleted $event): void
    {
        if ($event instanceof ReviewRejected && $event->previousStatus !== 'approved') {
            return;
        }

        if ($event instanceof ReviewSelfDeleted && $event->previousModerationStatus !== 'approved') {
            return;
        }

        $subjectType = $event->reviewType;
        $subjectId = $event->subjectId;

        $aggregate = $this->aggregationService->recompute($subjectType, $subjectId);

        if ($subjectType === 'service') {
            $this->serviceRatingWriter->update($subjectId, $aggregate['average'], $aggregate['count']);

            $publicId = (string) (DB::table('services')->where('id', $subjectId)->value('public_id') ?? '');

            DB::afterCommit(fn () => event(new ServiceRatingRecomputed(
                serviceId: $subjectId,
                servicePublicId: $publicId,
                newAverage: $aggregate['average'],
                count: $aggregate['count'],
            )));
        } else {
            $this->vendorRatingWriter->update($subjectId, $aggregate['average'], $aggregate['count']);

            $publicId = (string) (DB::table('vendor_profiles')->where('id', $subjectId)->value('public_id') ?? '');

            DB::afterCommit(fn () => event(new VendorRatingRecomputed(
                vendorProfileId: $subjectId,
                vendorPublicId: $publicId,
                newAverage: $aggregate['average'],
                count: $aggregate['count'],
            )));
        }
    }
}
