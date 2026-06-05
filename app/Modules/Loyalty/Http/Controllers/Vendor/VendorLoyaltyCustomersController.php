<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Vendor;

use App\Modules\Loyalty\Application\Actions\AdjustLoyaltyBalanceAction;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 16.3–16.5 — the vendor's loyalty members. Privacy: customer
 * identity is masked (first name + last initial, no email/phone) — same
 * convention as public reviews.
 */
class VendorLoyaltyCustomersController
{
    /** 16.3 — per-customer balances on this vendor's program. */
    public function customers(Request $request): JsonResponse
    {
        $vendorId = $this->vendorProfileId($request);

        $rows = DB::table('loyalty_ledger as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('l.vendor_profile_id', $vendorId)
            ->groupBy('l.user_id', 'u.name')
            ->orderByDesc(DB::raw('MAX(l.id)'))
            ->limit(100)
            ->get(['l.user_id', 'u.name', DB::raw("SUM(CASE WHEN l.direction = 'earn' THEN l.points ELSE -l.points END) as balance")])
            ->map(fn (object $row): array => [
                'customer_ref' => (string) $row->user_id,
                'customer_name' => $this->maskName((string) $row->name),
                'balance' => (int) $row->balance,
            ])
            ->values();

        return ApiResponse::success($rows);
    }

    /** 16.4 — the vendor's loyalty ledger (masked identities). */
    public function transactions(Request $request): JsonResponse
    {
        $page = LoyaltyLedgerEntry::query()
            ->where('vendor_profile_id', $this->vendorProfileId($request))
            ->with('user:id,name')
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return ApiResponse::success(
            collect($page->items())->map(fn (LoyaltyLedgerEntry $entry): array => [
                'public_id' => $entry->public_id,
                'customer_name' => $this->maskName((string) ($entry->user->name ?? '')),
                'direction' => $entry->direction,
                'points' => (int) $entry->points,
                'balance_after' => (int) $entry->balance_after,
                'reason' => $entry->reason,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->values(),
            ['next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages()],
        );
    }

    /** 16.5 — manual point adjustment with mandatory bilingual reason. */
    public function adjust(Request $request, int $customerId, AdjustLoyaltyBalanceAction $action): JsonResponse
    {
        $validated = $request->validate([
            'points_delta' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'reason_en' => ['required', 'string', 'max:255'],
            'reason_ar' => ['required', 'string', 'max:255'],
        ]);

        $vendorId = $this->vendorProfileId($request);

        // The customer must already be a member of this vendor's program.
        abort_unless(
            LoyaltyLedgerEntry::query()->where('vendor_profile_id', $vendorId)->where('user_id', $customerId)->exists(),
            404,
            'Customer is not a member of this loyalty program.',
        );

        $entry = $action->execute(
            $customerId,
            $vendorId,
            (int) $validated['points_delta'],
            $validated['reason_en'],
            $validated['reason_ar'],
            (int) $request->user()->id,
        );

        return ApiResponse::success([
            'public_id' => $entry->public_id,
            'direction' => $entry->direction,
            'points' => (int) $entry->points,
            'balance_after' => (int) $entry->balance_after,
        ], [], 201);
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? '';
        $lastInitial = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1).'.' : '';

        return trim($first.' '.$lastInitial);
    }

    private function vendorProfileId(Request $request): int
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return (int) $vendor->id;
    }
}
