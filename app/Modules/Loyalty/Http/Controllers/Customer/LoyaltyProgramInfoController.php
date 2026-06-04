<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Customer;

use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Contracts\VendorLookup;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 15.2 / 15.3 — per-vendor loyalty ledger history + earning/redemption
 * rules for the customer loyalty screen.
 */
class LoyaltyProgramInfoController
{
    public function __construct(
        private readonly LoyaltyProgramRepository $programs,
        private readonly VendorLookup $vendors,
    ) {}

    public function history(Request $request, string $vendorPublicId): JsonResponse
    {
        $program = $this->resolveProgram($vendorPublicId);

        $page = LoyaltyLedgerEntry::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('vendor_profile_id', (int) $program->vendor_profile_id)
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return ApiResponse::success(
            collect($page->items())->map(fn (LoyaltyLedgerEntry $entry): array => [
                'public_id' => $entry->public_id,
                'direction' => $entry->direction,
                'points' => (int) $entry->points,
                'balance_after' => (int) $entry->balance_after,
                'reason' => $entry->reason,
                'expires_at' => $entry->expires_at?->toIso8601String(),
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->values(),
            ['next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages()],
        );
    }

    public function rules(string $vendorPublicId): JsonResponse
    {
        $program = $this->resolveProgram($vendorPublicId);
        $locale = app()->getLocale();

        return ApiResponse::success([
            'program' => [
                'points_per_currency_unit' => $program->points_per_currency_unit,
                'points_value_minor' => (int) $program->points_value_minor,
                'points_value_currency' => $program->points_value_currency,
                'min_points_to_redeem' => (int) $program->min_points_to_redeem,
                'max_redeem_pct' => (int) $program->max_redeem_pct,
                'points_expire_after_days' => $program->points_expire_after_days,
            ],
            'rules' => LoyaltyRule::query()
                ->where('loyalty_program_id', $program->id)
                ->where('is_active', true)
                ->get()
                ->map(fn (LoyaltyRule $rule): array => [
                    'public_id' => $rule->public_id,
                    'rule_kind' => $rule->rule_kind,
                    'multiplier' => $rule->multiplier,
                    'label' => $rule->getTranslation('label', $locale, useFallbackLocale: true),
                    'starts_at' => $rule->starts_at?->toIso8601String(),
                    'ends_at' => $rule->ends_at?->toIso8601String(),
                ])->values()->all(),
        ]);
    }

    private function resolveProgram(string $vendorPublicId): object
    {
        $program = $this->programs->findByPublicId($vendorPublicId);
        abort_if($program === null, 404, __('loyalty::loyalty.errors.program_not_found'));
        abort_unless($this->vendors->isApproved((int) $program->vendor_profile_id), 404);

        return $program;
    }
}
