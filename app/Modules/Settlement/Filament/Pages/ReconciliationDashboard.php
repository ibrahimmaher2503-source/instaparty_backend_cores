<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Pages;

use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ReconciliationDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'settlement::filament.pages.reconciliation-dashboard';

    protected static ?int $navigationSort = 35;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    public static function getNavigationLabel(): string
    {
        return __('settlement.reconciliation_dashboard.nav_label');
    }

    public function getTitle(): string
    {
        return __('settlement.reconciliation_dashboard.title');
    }

    public function getOpenHighFindingsCount(): int
    {
        return (int) DB::table('reconciliation_findings')
            ->where('severity', ReconciliationFindingSeverity::High->value)
            ->whereNull('resolution')
            ->count();
    }

    public function getTotalAutoRepairedToday(): int
    {
        return (int) DB::table('reconciliation_findings')
            ->where('resolution', 'auto_repaired')
            ->whereDate('detected_at', today())
            ->count();
    }

    /** @return array<int, array{date: string, runs: int}> */
    public function getLast7DaysRunTrend(): array
    {
        $counts = DB::table('reconciliation_runs')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as runs')
            ->whereDate('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->pluck('runs', 'day');

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $trend[] = ['date' => $day, 'runs' => (int) ($counts[$day] ?? 0)];
        }

        return $trend;
    }
}
