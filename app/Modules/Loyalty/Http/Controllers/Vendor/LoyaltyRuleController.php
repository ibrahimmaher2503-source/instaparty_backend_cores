<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Vendor;

use App\Modules\Loyalty\Application\Actions\ConfigureLoyaltyProgramAction;
use App\Modules\Loyalty\Application\DTOs\ProgramDraft;
use App\Modules\Loyalty\Application\DTOs\RuleDraft;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Http\Requests\Vendor\StoreLoyaltyRuleRequest;
use App\Modules\Loyalty\Http\Resources\LoyaltyProgramResource;
use Illuminate\Http\JsonResponse;

class LoyaltyRuleController
{
    public function __construct(
        private readonly ConfigureLoyaltyProgramAction $configure,
        private readonly LoyaltyProgramRepository $programs,
    ) {}

    public function store(StoreLoyaltyRuleRequest $request): JsonResponse
    {
        $vendorProfileId = auth()->user()->vendorProfile->id;
        $program = $this->programs->findByVendor($vendorProfileId);
        abort_if($program === null, 404, __('loyalty::loyalty.errors.program_not_found'));

        $draft = ProgramDraft::fromRequest($program->toArray());
        $ruleDraft = RuleDraft::fromRequest($request->validated());
        $program = $this->configure->execute($vendorProfileId, $draft, $ruleDraft);

        return (new LoyaltyProgramResource($program->load('activeRule')))->response()->setStatusCode(201);
    }
}
