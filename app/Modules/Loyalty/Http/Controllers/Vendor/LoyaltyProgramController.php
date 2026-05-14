<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Vendor;

use App\Modules\Loyalty\Application\Actions\ConfigureLoyaltyProgramAction;
use App\Modules\Loyalty\Application\DTOs\ProgramDraft;
use App\Modules\Loyalty\Application\DTOs\RuleDraft;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Http\Requests\Vendor\StoreLoyaltyProgramRequest;
use App\Modules\Loyalty\Http\Requests\Vendor\UpdateLoyaltyProgramRequest;
use App\Modules\Loyalty\Http\Resources\LoyaltyProgramResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class LoyaltyProgramController
{
    public function __construct(
        private readonly ConfigureLoyaltyProgramAction $configure,
        private readonly LoyaltyProgramRepository $programs,
    ) {}

    public function store(StoreLoyaltyProgramRequest $request): JsonResponse
    {
        $program = $this->configure->execute(
            $this->vendorProfileId($request),
            ProgramDraft::fromRequest($request->validated()),
            $this->ruleDrafts($request->validated()),
        );

        return ApiResponse::success(
            (new LoyaltyProgramResource($program->load('activeRules')))->resolve($request),
            status: 201,
        );
    }

    public function show(StoreLoyaltyProgramRequest $request): JsonResponse
    {
        $program = $this->programs->findByVendor($this->vendorProfileId($request));
        abort_if($program === null, 404, __('loyalty::loyalty.errors.program_not_found'));

        return ApiResponse::success(
            (new LoyaltyProgramResource($program->load('activeRules')))->resolve($request),
        );
    }

    public function update(UpdateLoyaltyProgramRequest $request): JsonResponse
    {
        $program = $this->configure->execute(
            $this->vendorProfileId($request),
            ProgramDraft::fromRequest($request->validated()),
            $this->ruleDrafts($request->validated()),
        );

        return ApiResponse::success(
            (new LoyaltyProgramResource($program->load('activeRules')))->resolve($request),
        );
    }

    private function vendorProfileId(\Illuminate\Http\Request $request): int
    {
        $user = $request->user();
        abort_if($user === null || $user->vendorProfile === null, 403);

        return (int) $user->vendorProfile->id;
    }

    /**
     * @return array<int, RuleDraft>|null
     */
    private function ruleDrafts(array $validated): ?array
    {
        if (! array_key_exists('rules', $validated)) {
            return null;
        }
        if ($validated['rules'] === null) {
            return [];
        }

        return array_map(fn (array $r) => RuleDraft::fromRequest($r), $validated['rules']);
    }
}
