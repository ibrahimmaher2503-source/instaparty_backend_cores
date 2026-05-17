<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Providers;

use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Settlement\Application\Actions\PostLedgerTransactionAction;
use App\Modules\Settlement\Application\Actions\ProjectWalletBalanceAction;
use App\Modules\Settlement\Application\Listeners\CalculateCommissionOnPaymentCapturedListener;
use App\Modules\Settlement\Application\Listeners\NotifyAdminOnHighSeverityFindingListener;
use App\Modules\Settlement\Application\Listeners\ReverseCommissionOnRefundCompletedListener;
use App\Modules\Settlement\Console\Commands\BackfillLedgerColumnsCommand;
use App\Modules\Settlement\Console\Commands\LedgerDiffCommand;
use App\Modules\Settlement\Console\Commands\LedgerInventoryCommand;
use App\Modules\Settlement\Console\Commands\ReconcileFinancialsCommand;
use App\Modules\Settlement\Console\Commands\SettleRunCommand;
use App\Modules\Settlement\Console\Commands\SnapshotWalletsCommand;
use App\Modules\Settlement\Domain\Contracts\CommissionRateResolver;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\ReconciliationDetector;
use App\Modules\Settlement\Domain\Contracts\WalletLocker;
use App\Modules\Settlement\Domain\Contracts\WalletProjector;
use App\Modules\Settlement\Domain\Events\ReconciliationFindingRaised;
use App\Modules\Settlement\Infrastructure\Detectors\EloquentReconciliationDetector;
use App\Modules\Settlement\Infrastructure\Locks\RedisWalletLocker;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentCommissionRateResolver;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentCommissionRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWithdrawalRepository;
use App\Modules\Shared\Http\Middleware\StartCausalChain;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SettlementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CommissionRateResolver::class, EloquentCommissionRateResolver::class);
        $this->app->bind(EloquentWalletRepository::class);
        $this->app->bind(EloquentWithdrawalRepository::class);
        $this->app->bind(EloquentCommissionRepository::class);

        // Phase 4.9 — ledger hardening contracts
        $this->app->bind(LedgerWriter::class, PostLedgerTransactionAction::class);
        $this->app->bind(WalletProjector::class, ProjectWalletBalanceAction::class);
        $this->app->bind(WalletLocker::class, RedisWalletLocker::class);
        $this->app->bind(ReconciliationDetector::class, EloquentReconciliationDetector::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'settlement');
        $this->loadViewsFrom(base_path('resources/views/vendor/settlement'), 'settlement');
        $this->loadRoutesFrom(__DIR__.'/../Routes/vendor.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        // Register StartCausalChain globally so every request seeds correlation/causation IDs.
        $this->app->make(Kernel::class)->pushMiddleware(StartCausalChain::class);

        $this->commands([
            BackfillLedgerColumnsCommand::class,
            LedgerDiffCommand::class,
            LedgerInventoryCommand::class,
            ReconcileFinancialsCommand::class,
            SettleRunCommand::class,
            SnapshotWalletsCommand::class,
        ]);

        Event::listen(
            PaymentCaptured::class,
            [CalculateCommissionOnPaymentCapturedListener::class, 'handle'],
        );

        Event::listen(
            RefundCompleted::class,
            [ReverseCommissionOnRefundCompletedListener::class, 'handle'],
        );

        Event::listen(
            ReconciliationFindingRaised::class,
            [NotifyAdminOnHighSeverityFindingListener::class, 'handle'],
        );
    }
}
