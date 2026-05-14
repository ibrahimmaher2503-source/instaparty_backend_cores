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

    /**
     * @param  array<int, RuleDraft>|null  $ruleDrafts  null = leave rules untouched; [] = deactivate all
     */
    public function execute(int $vendorProfileId, ProgramDraft $draft, ?array $ruleDrafts = null): LoyaltyProgram
    {
        return DB::transaction(function () use ($vendorProfileId, $draft, $ruleDrafts) {
            $existing = $this->programs->findByVendor($vendorProfileId);

            $program = $existing
                ? $this->programs->update($existing, $draft)
                : $this->programs->create($draft, $vendorProfileId);

            if ($ruleDrafts !== null) {
                foreach ($this->rules->activeRulesFor((int) $program->id) as $activeRule) {
                    $this->rules->deactivate($activeRule);
                }

                foreach ($ruleDrafts as $ruleDraft) {
                    $this->rules->create((int) $program->id, $ruleDraft);
                }
            }

            DB::afterCommit(fn () => event(new LoyaltyProgramConfigured($program)));

            return $program;
        });
    }
}
