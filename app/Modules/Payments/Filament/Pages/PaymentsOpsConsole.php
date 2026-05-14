<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Pages;

use App\Modules\Payments\Application\Actions\ManualCapturePaymentAction;
use App\Modules\Payments\Application\Actions\MarkPaymentAbandonedAction;
use App\Modules\Payments\Application\Actions\OpenChargebackAction;
use App\Modules\Payments\Application\Actions\ReplayWebhookAction;
use App\Modules\Payments\Application\Actions\ResolveChargebackAction;
use App\Modules\Payments\Application\Actions\RetryFailedPaymentAction;
use App\Modules\Payments\Application\Actions\VoidStuckAuthorizationAction;
use App\Modules\Payments\Application\DTOs\OpenChargebackDto;
use App\Modules\Payments\Application\DTOs\ResolveChargebackDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Enums\ChargebackStatus;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\GatewayHealthPing;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentChargeback;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsOpsConsole extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'payments::filament.pages.payments-ops-console';

    public string $activeTab = 'failed_payments';

    public static function getNavigationLabel(): string
    {
        return 'Ops Console';
    }

    public function getTitle(): string
    {
        return 'Payments Operations Console';
    }

    public function tabs(): array
    {
        return [
            'failed_payments' => 'Failed Payments',
            'stuck_auths' => 'Stuck Authorizations',
            'webhook_replay' => 'Webhook Replay',
            'chargebacks' => 'Chargebacks',
            'gateway_health' => 'Gateway Health',
            'reconciliation' => 'Reconciliation Diff',
        ];
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return match ($this->activeTab) {
            'failed_payments' => $this->failedPaymentsTable($table),
            'stuck_auths' => $this->stuckAuthsTable($table),
            'webhook_replay' => $this->webhookReplayTable($table),
            'chargebacks' => $this->chargebacksTable($table),
            'gateway_health' => $this->gatewayHealthTable($table),
            'reconciliation' => $this->reconciliationTable($table),
            default => $this->failedPaymentsTable($table),
        };
    }

    private function failedPaymentsTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Payment::query()
                ->where('status', PaymentStatus::Failed)
                ->latest()
            )
            ->columns([
                TextColumn::make('public_id')->label('ID')->searchable()->limit(12),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('gateway_ref')->label('Gateway Ref')->limit(20),
                TextColumn::make('amount_minor')->label('Amount')->money('EGP', divideBy: 100)->sortable(),
                TextColumn::make('failure_code')->badge()->color('danger'),
                TextColumn::make('created_at')->label('Failed At')->dateTime()->sortable(),
            ])
            ->actions([
                TableAction::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Payment $record): void {
                        app(RetryFailedPaymentAction::class)->execute($record->id, auth()->id());
                        Notification::make()->title('Payment queued for retry.')->success()->send();
                    }),

                TableAction::make('abandon')
                    ->label('Mark Abandoned')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Payment $record): void {
                        app(MarkPaymentAbandonedAction::class)->execute($record->id, auth()->id());
                        Notification::make()->title('Payment marked as abandoned.')->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function stuckAuthsTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Payment::query()
                ->where('status', PaymentStatus::Authorized)
                ->where('created_at', '<', now()->subHours(24))
                ->latest()
            )
            ->columns([
                TextColumn::make('public_id')->label('ID')->searchable()->limit(12),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('gateway_ref')->label('Gateway Ref')->limit(20),
                TextColumn::make('amount_minor')->label('Amount')->money('EGP', divideBy: 100)->sortable(),
                TextColumn::make('created_at')->label('Authorized At')->dateTime()->sortable(),
            ])
            ->actions([
                TableAction::make('capture')
                    ->label('Manual Capture')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason for manual capture')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        app(ManualCapturePaymentAction::class)->execute($record->id, auth()->id(), $data['reason']);
                        Notification::make()->title('Payment captured manually.')->success()->send();
                    }),

                TableAction::make('void')
                    ->label('Void Authorization')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason for void')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        app(VoidStuckAuthorizationAction::class)->execute($record->id, auth()->id(), $data['reason']);
                        Notification::make()->title('Authorization voided.')->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'asc');
    }

    private function webhookReplayTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => GatewayWebhookLog::query()
                ->where('signature_valid', true)
                ->latest()
            )
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('event_type')->badge()->color('info'),
                TextColumn::make('processed_at')->label('Processed At')->dateTime()->placeholder('Not processed'),
                TextColumn::make('processing_error')->label('Error')->limit(40)->placeholder('—'),
                TextColumn::make('created_at')->label('Received At')->dateTime()->sortable(),
            ])
            ->actions([
                TableAction::make('replay')
                    ->label('Replay')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Re-dispatch this webhook through the processor. Idempotency is guaranteed — no double-charge is possible.')
                    ->action(function (GatewayWebhookLog $record): void {
                        app(ReplayWebhookAction::class)->execute($record->id, auth()->id());
                        Notification::make()->title('Webhook replayed.')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function chargebacksTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PaymentChargeback::query()->with('payment')->latest('opened_at'))
            ->columns([
                TextColumn::make('public_id')->label('ID')->searchable()->limit(12),
                TextColumn::make('payment.gateway_ref')->label('Gateway Ref')->limit(20),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ChargebackStatus $state): string => $state->color()),
                TextColumn::make('amount_minor')->label('Amount')->money('EGP', divideBy: 100)->sortable(),
                TextColumn::make('gateway_case_id')->label('Case ID')->placeholder('—'),
                TextColumn::make('opened_at')->label('Opened At')->dateTime()->sortable(),
                TextColumn::make('resolved_at')->label('Resolved At')->dateTime()->placeholder('Pending'),
            ])
            ->headerActions([
                TableAction::make('open_chargeback')
                    ->label('Open Chargeback')
                    ->icon('heroicon-o-plus-circle')
                    ->color('danger')
                    ->form([
                        TextInput::make('payment_id')->label('Payment ID')->integer()->required(),
                        Textarea::make('reason_en')->label('Reason (EN)')->required(),
                        Textarea::make('reason_ar')->label('Reason (AR)')->required(),
                        TextInput::make('amount_minor')->label('Amount (minor units)')->integer()->required(),
                        TextInput::make('gateway_case_id')->label('Gateway Case ID')->nullable(),
                    ])
                    ->action(function (array $data): void {
                        app(OpenChargebackAction::class)->execute(new OpenChargebackDto(
                            paymentId: (int) $data['payment_id'],
                            adminUserId: auth()->id(),
                            reason: ['en' => $data['reason_en'], 'ar' => $data['reason_ar']],
                            amountMinor: (int) $data['amount_minor'],
                            amountCurrency: 'EGP',
                            gatewayCaseId: $data['gateway_case_id'] ?? null,
                        ));
                        Notification::make()->title(__('payments::chargebacks.opened_successfully'))->success()->send();
                    }),
            ])
            ->actions([
                TableAction::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->form([
                        Select::make('status')
                            ->label('Resolution')
                            ->options([
                                ChargebackStatus::Won->value => 'Won (restore vendor credit)',
                                ChargebackStatus::Lost->value => 'Lost (finalize debit)',
                            ])
                            ->required(),
                        Textarea::make('admin_notes_en')->label('Admin Notes (EN)'),
                        Textarea::make('admin_notes_ar')->label('Admin Notes (AR)'),
                    ])
                    ->action(function (PaymentChargeback $record, array $data): void {
                        app(ResolveChargebackAction::class)->execute(new ResolveChargebackDto(
                            chargebackId: $record->id,
                            adminUserId: auth()->id(),
                            status: ChargebackStatus::from($data['status']),
                            adminNotes: isset($data['admin_notes_en'])
                                ? ['en' => $data['admin_notes_en'], 'ar' => $data['admin_notes_ar'] ?? '']
                                : null,
                        ));
                        Notification::make()->title(__('payments::chargebacks.resolved_successfully', ['status' => $data['status']]))->success()->send();
                    })
                    ->visible(fn (PaymentChargeback $record): bool => ! $record->isResolved()),
            ])
            ->defaultSort('opened_at', 'desc');
    }

    private function gatewayHealthTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => GatewayHealthPing::query()
                ->where('checked_at', '>=', now()->subHours(24))
                ->latest('checked_at')
            )
            ->columns([
                TextColumn::make('gateway_code')->badge()->color('gray'),
                TextColumn::make('success')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'OK' : 'FAIL')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('latency_ms')->label('Latency (ms)')->sortable(),
                TextColumn::make('error_message')->label('Error')->limit(60)->placeholder('—'),
                TextColumn::make('checked_at')->label('Checked At')->dateTime()->sortable(),
            ])
            ->defaultSort('checked_at', 'desc');
    }

    private function reconciliationTable(Table $table): Table
    {
        $platformCount = Payment::query()
            ->where('status', PaymentStatus::Captured)
            ->whereDate('captured_at', today())
            ->count();

        try {
            $gatewayCount = app(PaymentGateway::class)->getTodayCapturedCount();
            $source = 'Live from Gateway';
        } catch (\Throwable) {
            $gatewayCount = null;
            $source = 'Gateway Unavailable';
        }

        $diff = $gatewayCount !== null ? ($gatewayCount - $platformCount) : null;

        // Use a simple static dataset rendered as a table
        return $table
            ->query(fn (): Builder => Payment::query()
                ->where('status', PaymentStatus::Captured)
                ->whereDate('captured_at', today())
                ->latest('captured_at')
            )
            ->columns([
                TextColumn::make('public_id')->label('Payment ID')->limit(12),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('gateway_ref')->label('Gateway Ref')->limit(20),
                TextColumn::make('amount_minor')->label('Amount')->money('EGP', divideBy: 100),
                TextColumn::make('captured_at')->label('Captured At')->dateTime()->sortable(),
            ])
            ->heading(sprintf(
                'Reconciliation — Platform: %d | Gateway: %s | Diff: %s | Source: %s',
                $platformCount,
                $gatewayCount ?? 'N/A',
                $diff !== null ? ($diff === 0 ? 'None ✓' : (string) $diff) : 'N/A',
                $source,
            ))
            ->defaultSort('captured_at', 'desc');
    }
}
