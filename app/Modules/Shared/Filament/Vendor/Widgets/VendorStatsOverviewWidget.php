<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Vendor\Widgets;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class VendorStatsOverviewWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        $vendorProfile = auth()->user()->vendorProfile;
        $vendorId = $vendorProfile->id;

        return Cache::remember("vendor_dashboard_stats_{$vendorId}", 300, function () use ($vendorId): array {
            $now = now();
            $startOfMonth = $now->copy()->startOfMonth();

            $bookingsThisMonth = BookingVendor::query()
                ->where('vendor_profile_id', $vendorId)
                ->whereNotIn('sub_status', [VendorSubStatus::Cancelled->value, VendorSubStatus::TimedOut->value])
                ->whereBetween('created_at', [$startOfMonth, $now])
                ->count();

            $revenueThisMonthMinor = WalletLedgerEntry::query()
                ->whereHas('wallet', fn ($q) => $q->where('owner_type', 'vendor')->where('owner_id', $vendorId))
                ->where('entry_type', LedgerEntryType::CommissionCredit->value)
                ->whereBetween('created_at', [$startOfMonth, $now])
                ->sum('amount_minor');

            $pendingServices = Service::query()
                ->where('vendor_profile_id', $vendorId)
                ->where('status', ServiceStatus::PendingReview)
                ->count();

            $pendingBookings = BookingVendor::query()
                ->where('vendor_profile_id', $vendorId)
                ->where('sub_status', VendorSubStatus::Pending)
                ->count();

            $activeBookings = BookingVendor::query()
                ->where('vendor_profile_id', $vendorId)
                ->whereIn('sub_status', [VendorSubStatus::Accepted->value, VendorSubStatus::InProgress->value])
                ->count();

            $avgRating = ServiceReview::query()
                ->whereHas('service', fn ($q) => $q->where('vendor_profile_id', $vendorId))
                ->where('moderation_status', ModerationStatus::Approved)
                ->avg('rating');

            $wallet = Wallet::query()
                ->where('owner_type', 'vendor')
                ->where('owner_id', $vendorId)
                ->where('currency', 'EGP')
                ->first();

            $balanceMinor = $wallet ? ($wallet->balance_minor - $wallet->pending_withdrawal_minor) : 0;
            $balanceEgp = number_format($balanceMinor / 100, 2);
            $revenueEgp = number_format($revenueThisMonthMinor / 100, 2);

            return [
                Stat::make(__('vendor-portal.dashboard.bookings_this_month'), $bookingsThisMonth)
                    ->icon('heroicon-o-calendar-days')
                    ->color('info'),

                Stat::make(__('vendor-portal.dashboard.revenue_this_month'), "EGP {$revenueEgp}")
                    ->icon('heroicon-o-banknotes')
                    ->color('success'),

                Stat::make(__('vendor-portal.dashboard.pending_services'), $pendingServices)
                    ->icon('heroicon-o-clock')
                    ->color($pendingServices > 0 ? 'warning' : 'gray'),

                Stat::make(__('vendor-portal.dashboard.pending_bookings'), $pendingBookings)
                    ->icon('heroicon-o-bell-alert')
                    ->color($pendingBookings > 0 ? 'danger' : 'gray'),

                Stat::make(__('vendor-portal.dashboard.active_bookings'), $activeBookings)
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary'),

                Stat::make(__('vendor-portal.dashboard.average_rating'), $avgRating ? number_format((float) $avgRating, 1) : '—')
                    ->icon('heroicon-o-star')
                    ->color('warning'),

                Stat::make(__('vendor-portal.dashboard.wallet_balance'), "EGP {$balanceEgp}")
                    ->icon('heroicon-o-wallet')
                    ->color('success'),
            ];
        });
    }
}
