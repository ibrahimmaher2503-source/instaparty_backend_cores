<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Application\Actions\ApproveAndMarkWithdrawalPaidAction;
use App\Modules\Settlement\Application\Actions\RejectWithdrawalAction;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource\Pages\ListWithdrawalsQueue;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
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
            ->where('status', WithdrawalStatus::Pending->value);
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
                    ->default('â€”'),

                Tables\Columns\TextColumn::make('requested_amount_minor')
                    ->money('EGP', divideBy: 100)
                    ->label('Amount')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (WithdrawalStatus $state): string => match ($state) {
                        WithdrawalStatus::Pending => 'warning',
                        WithdrawalStatus::Approved => 'info',
                        WithdrawalStatus::Paid => 'success',
                        WithdrawalStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (WithdrawalStatus $state): string => ucfirst($state->value)),

                Tables\Columns\TextColumn::make('requested_at')
                    ->dateTime()
                    ->label('Requested At')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        WithdrawalStatus::Pending->value => 'Pending',
                        WithdrawalStatus::Approved->value => 'Approved',
                        WithdrawalStatus::Paid->value => 'Paid',
                        WithdrawalStatus::Rejected->value => 'Rejected',
                    ])
                    ->label('Status'),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve & Mark Paid')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        FileUpload::make('bank_proof')
                            ->label('Bank Transfer Proof')
                            ->required()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(10240)
                            ->disk('local')
                            ->directory('withdrawal-proofs'),
                    ])
                    ->action(function (Withdrawal $record, array $data): void {
                        $path = $data['bank_proof'];

                        // Filament FileUpload returns a path string; wrap into UploadedFile for the Action
                        $storagePath = storage_path('app/'.$path);
                        $uploadedFile = new UploadedFile(
                            path: $storagePath,
                            originalName: basename($storagePath),
                            mimeType: mime_content_type($storagePath) ?: 'application/octet-stream',
                            error: null,
                            test: false,
                        );

                        app(ApproveAndMarkWithdrawalPaidAction::class)->execute($record, $uploadedFile, auth()->user());

                        Notification::make()
                            ->title('Withdrawal approved and marked paid')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Withdrawal $record): bool => $record->status === WithdrawalStatus::Pending
                        && auth()->user()?->can('approve_withdrawal')
                    ),

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
                    ->visible(fn (Withdrawal $record): bool => $record->status === WithdrawalStatus::Pending
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
