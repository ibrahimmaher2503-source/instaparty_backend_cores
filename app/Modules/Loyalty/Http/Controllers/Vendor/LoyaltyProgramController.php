<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Vendor;

use App\Modules\Loyalty\Application\Actions\ConfigureLoyaltyProgramAction;
use App\Modules\Loyalty\Application\DTOs\ProgramDraft;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Http\Requests\Vendor\StoreLoyaltyProgramRequest;
use App\Modules\Loyalty\Http\Requests\Vendor\UpdateLoyaltyProgramRequest;
use App\Modules\Loyalty\Http\Resources\LoyaltyProgramResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyProgramController
{
    public function __construct(
        private readonly ConfigureLoyaltyProgramAction $configure,
        private readonly LoyaltyProgramRepository $programs,
    ) {}

    public function store(StoreLoyaltyProgramRequest $request): JsonResponse
    {
        $program = $this->configure->execute(auth()->user()->vendorProfile->id, ProgramDraft::fromRequest($request->validated()));
        return (new LoyaltyProgramResource($program->load('activeRule')))->response()->setStatusCode(201);
    }

    public function show(Request $request): JsonResponse
    {
        $program = $this->programs->findByVendor(auth()->user()->vendorProfile->id);
        abort_if($program === null, 404, __('loyalty::loyalty.errors.program_not_found'));
        return (new LoyaltyProgramResource($program->load('activeRule')))->response();
    }

    public function update(UpdateLoyaltyProgramRequest $request): JsonResponse
    {
        $program = $this->configure->execute(auth()->user()->vendorProfile->id, ProgramDraft::fromRequest($request->validated()));
        return (new LoyaltyProgramResource($program->load('activeRule')))->response();
    }
}
