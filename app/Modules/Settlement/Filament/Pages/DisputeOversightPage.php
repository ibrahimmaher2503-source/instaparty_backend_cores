<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class DisputeOversightPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static string  $view = 'settlement::filament.pages.dispute-oversight';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    public static function getNavigationLabel(): string
    {
        return __('settlement.dispute_oversight');
    }

    public function getStats(): array
    {
        $pendingRefunds = DB::table('refunds')
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        $pendingWithdrawals = DB::table('withdrawals')
            ->where('status', 'pending')
            ->count();

        $totalDisputedMinor = (int) DB::table('refunds')
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount_minor');

        return [
            'pending_refunds'    => $pendingRefunds,
            'pending_withdrawals' => $pendingWithdrawals,
            'total_disputed'     => $totalDisputedMinor,
        ];
    }

    public function getPendingRefunds(): \Illuminate\Support\Collection
    {
        return DB::table('refunds')
            ->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->whereIn('refunds.status', ['pending', 'processing'])
            ->select([
                'refunds.id',
                'refunds.public_id',
                'refunds.status',
                'refunds.amount_minor',
                'refunds.reason_notes',
                'refunds.created_at',
                'payments.gateway_ref',
            ])
            ->orderByDesc('refunds.created_at')
            ->limit(50)
            ->get();
    }

    public function getPendingWithdrawals(): \Illuminate\Support\Collection
    {
        return DB::table('withdrawals')
            ->join('wallets', 'wallets.id', '=', 'withdrawals.wallet_id')
            ->where('withdrawals.status', 'pending')
            ->select([
                'withdrawals.id',
                'withdrawals.public_id',
                'withdrawals.status',
                'withdrawals.requested_amount_minor',
                'withdrawals.requested_amount_currency',
                'withdrawals.created_at',
                'wallets.owner_type',
                'wallets.owner_id',
            ])
            ->orderByDesc('withdrawals.created_at')
            ->limit(50)
            ->get();
    }
}
