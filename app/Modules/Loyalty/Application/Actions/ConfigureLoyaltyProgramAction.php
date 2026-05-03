<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\ProgramDraft;
use App\Modules\Loyalty\Application\DTOs\RuleDraft;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRuleRepository;
use App\Modules\Loyalty\Domain\Events\LoyaltyProgramConfigured;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Illuminate\Support\Facades\DB;

class ConfigureLoyaltyProgramAction
{
    public function __construct(
        private readonly LoyaltyProgramRepository $programs,
        private readonly LoyaltyRuleRepository $rules,
    ) {}

    public function execute(int $vendorProfileId, ProgramDraft $draft, ?RuleDraft $ruleDraft = null): LoyaltyProgram
    {
        return DB::transaction(function () use ($vendorProfileId, $draft, $ruleDraft) {
            $existing = $this->programs->findByVendor($vendorProfileId);

            if ($existing) {
                $program = $this->programs->update($existing, $draft);
            } else {
                $program = $this->programs->create($draft, $vendorProfileId);
            }

            if ($ruleDraft !== null) {
                $this->rules->replaceActive($program->id, $ruleDraft);
            }

            DB::afterCommit(fn () => event(new LoyaltyProgramConfigured($program)));

            return $program;
        });
    }
}
