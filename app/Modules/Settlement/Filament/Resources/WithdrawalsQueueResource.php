<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Application\Actions\MarkWithdrawalPaidAction;
use App\Modules\Settlement\Application\Actions\RejectWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\MarkWithdrawalPaidInput;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource\Pages\ListWithdrawalsQueue;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

class WithdrawalsQueueResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static ?string $slug = 'settlement-withdrawals';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.withdrawals_queue');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.withdrawal.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.withdrawal.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('status', [WithdrawalStatus::Pending->value, WithdrawalStatus::Approved->value]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('public_id')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('vendorProfile.business_name')
                    ->label('Vendor')
                    ->searchable()
                    ->default('—'),

                Tables\Columns\TextColumn::make('requested_amount_minor')
                    ->money('EGP', divideBy: 100)
                    ->label('Amount')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (WithdrawalStatus $state): string => match ($state) {
                        WithdrawalStatus::Pending  => 'warning',
                        WithdrawalStatus::Approved => 'info',
                        WithdrawalStatus::Paid     => 'success',
                        WithdrawalStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (WithdrawalStatus $state): string => ucfirst($state->value)),

                Tables\Columns\TextColumn::make('requested_at')
                    ->dateTime()
                    ->label('Requested At')
                    ->sortable(),

                Tables\Columns\TextColumn::make('approved_at')
                    ->dateTime()
                    ->label('Approved At')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        WithdrawalStatus::Pending->value  => 'Pending',
                        WithdrawalStatus::Approved->value => 'Approved',
                        WithdrawalStatus::Paid->value     => 'Paid',
                        WithdrawalStatus::Rejected->value => 'Rejected',
                    ])
                    ->label('Status'),
            ])
            ->actions([
                // ─── Step 1: Approve (only on Pending withdrawals) ─────────────────
                Action::make('approve')
                    ->label(__('settlement.actions.approve'))
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(__('settlement.actions.approve'))
                    ->modalDescription(__('settlement.actions.approve_description'))
                    ->action(function (Withdrawal $record): void {
                        try {
                            app(ApproveWithdrawalAction::class)->execute($record, auth()->user());

                            Notification::make()
                                ->title(__('settlement.notifications.withdrawal_approved'))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('settlement.errors.withdrawal_state'))
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (Withdrawal $record): bool => $record->getRawOriginal('status') === WithdrawalStatus::Pending->value
                        && auth()->user()?->can('withdrawal.approve')
                    ),

                // ─── Step 2: Mark Paid (only on Approved withdrawals) ───────────────
                Action::make('markPaid')
                    ->label(__('settlement.actions.mark_paid'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        TextInput::make('bank_transfer_reference')
                            ->label(__('settlement.fields.bank_transfer_reference'))
                            ->required()
                            ->maxLength(120),

                        FileUpload::make('proof_file')
                            ->label(__('settlement.fields.transfer_proof'))
                            ->required()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(10240)
                            ->disk('local')
                            ->directory('withdrawal-proofs'),

                        Tabs::make('payment_note')
                            ->tabs([
                                Tabs\Tab::make('English')
                                    ->schema([
                                        Textarea::make('admin_payment_note_en')
                                            ->label(__('settlement.fields.payment_note_en'))
                                            ->maxLength(1000)
                                            ->rows(3),
                                    ]),
                                Tabs\Tab::make('العربية')
                                    ->schema([
                                        Textarea::make('admin_payment_note_ar')
                                            ->label(__('settlement.fields.payment_note_ar'))
                                            ->maxLength(1000)
                                            ->rows(3),
                                    ]),
                            ]),
                    ])
                    ->action(function (Withdrawal $record, array $data): void {
                        $path = $data['proof_file'];
                        $storagePath = storage_path('app/'.$path);
                        $uploadedFile = new UploadedFile(
                            path: $storagePath,
                            originalName: basename($storagePath),
                            mimeType: mime_content_type($storagePath) ?: 'application/octet-stream',
                            error: null,
                            test: false,
                        );

                        $paymentNote = array_filter([
                            'en' => $data['admin_payment_note_en'] ?? null,
                            'ar' => $data['admin_payment_note_ar'] ?? null,
                        ]);

                        try {
                            $input = new MarkWithdrawalPaidInput(
                                bankTransferReference: $data['bank_transfer_reference'],
                                proofFile: $uploadedFile,
                                paymentNote: $paymentNote,
                            );

                            app(MarkWithdrawalPaidAction::class)->execute($record, $input, auth()->user());

                            Notification::make()
                                ->title(__('settlement.notifications.withdrawal_paid'))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('settlement.errors.mark_paid_failed'))
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (Withdrawal $record): bool => $record->getRawOriginal('status') === WithdrawalStatus::Approved->value
                        && auth()->user()?->can('withdrawal.mark_paid')
                    ),

                // ─── Reject (only on Pending) ────────────────────────────────────
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('rejected_reason_en')
                            ->label('Rejection Reason (English)')
                            ->required()
                            ->rows(3),

                        Textarea::make('rejected_reason_ar')
                            ->label('Rejection Reason (Arabic)')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Withdrawal $record, array $data): void {
                        app(RejectWithdrawalAction::class)->execute(
                            withdrawal: $record,
                            rejectedReason: [
                                'en' => $data['rejected_reason_en'],
                                'ar' => $data['rejected_reason_ar'],
                            ],
                            admin: auth()->user(),
                        );

                        Notification::make()
                            ->title('Withdrawal rejected')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (Withdrawal $record): bool => $record->getRawOriginal('status') === WithdrawalStatus::Pending->value
                        && auth()->user()?->can('reject_withdrawal')
                    ),

                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawalsQueue::route('/'),
        ];
    }
}
