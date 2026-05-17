<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Modules\Settlement\Filament\Resources\WithdrawalResource\Pages\ViewWithdrawal;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WithdrawalResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static ?string $slug = 'settlement-withdrawal-audit';

    protected static ?int $navigationSort = 35;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    public static function getNavigationLabel(): string
    {
        return 'Withdrawal Audit';
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.withdrawal.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.withdrawal.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // ── Section 1: Request ─────────────────────────────────────────────
            Section::make('Request Details')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('public_id')
                                ->label('Reference')
                                ->copyable(),

                            TextEntry::make('status')
                                ->label(__('settlement.columns.status'))
                                ->badge()
                                ->color(fn (WithdrawalStatus $state): string => match ($state) {
                                    WithdrawalStatus::Pending  => 'warning',
                                    WithdrawalStatus::Approved => 'info',
                                    WithdrawalStatus::Paid     => 'success',
                                    WithdrawalStatus::Rejected => 'danger',
                                })
                                ->formatStateUsing(fn (WithdrawalStatus $state): string => ucfirst($state->value)),

                            TextEntry::make('requested_amount_minor')
                                ->label('Amount')
                                ->money('EGP', divideBy: 100),

                            TextEntry::make('requested_at')
                                ->label('Requested At')
                                ->dateTime(),

                            TextEntry::make('vendorProfile.business_name')
                                ->label('Vendor')
                                ->default('—'),

                            TextEntry::make('requestedBy.name')
                                ->label('Requested By')
                                ->default('—'),
                        ]),
                ]),

            // ── Section 2: Approval ────────────────────────────────────────────
            Section::make('Approval')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('approved_at')
                                ->label(__('settlement.fields.approved_at'))
                                ->dateTime()
                                ->placeholder('Not yet approved'),

                            TextEntry::make('approvedByAdmin.name')
                                ->label(__('settlement.fields.approved_by'))
                                ->placeholder('—'),
                        ]),
                ]),

            // ── Section 3: Payment ──────────────────────────────────────────────
            Section::make('Payment')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('paid_at')
                                ->label('Paid At')
                                ->dateTime()
                                ->placeholder('Not paid yet'),

                            TextEntry::make('paidByAdmin.name')
                                ->label(__('settlement.fields.paid_by'))
                                ->placeholder('—'),

                            TextEntry::make('bank_transfer_reference')
                                ->label(__('settlement.fields.bank_transfer_reference'))
                                ->copyable()
                                ->placeholder('—'),

                            TextEntry::make('admin_payment_note_en')
                                ->label('Payment Note (EN)')
                                ->state(fn (Withdrawal $record): string => $record->admin_payment_note['en'] ?? '—')
                                ->placeholder('—'),

                            TextEntry::make('admin_payment_note_ar')
                                ->label('Payment Note (AR)')
                                ->state(fn (Withdrawal $record): string => $record->admin_payment_note['ar'] ?? '—')
                                ->placeholder('—'),
                        ]),
                ])
                ->visible(fn (): bool => auth()->user()?->can('withdrawal.view_audit') ?? false),

            // ── Section 4: Proof ────────────────────────────────────────────────
            Section::make('Transfer Proof')
                ->schema([
                    TextEntry::make('bank_proof_file')
                        ->label('Proof File')
                        ->state(function (Withdrawal $record): string {
                            $media = $record->getFirstMedia('bank_proof');
                            if ($media === null) {
                                return 'No proof attached';
                            }
                            try {
                                $url = $media->getTemporaryUrl(now()->addMinutes(15));

                                return 'Proof available — ' . $media->file_name;
                            } catch (\Exception) {
                                return 'Proof attached (' . $media->file_name . ')';
                            }
                        })
                        ->copyable(fn (Withdrawal $record): bool => $record->getFirstMedia('bank_proof') !== null),
                ])
                ->visible(fn (): bool => auth()->user()?->can('withdrawal.view_audit') ?? false),

            // ── Section 5: Ledger Links ─────────────────────────────────────────
            Section::make('Ledger Entries')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('reserved_ledger_entry_id')
                                ->label('Reserve Ledger Entry')
                                ->placeholder('—'),

                            TextEntry::make('settled_ledger_entry_id')
                                ->label('Settle Ledger Entry')
                                ->placeholder('—'),

                            TextEntry::make('rejected_ledger_entry_id')
                                ->label('Reject Ledger Entry')
                                ->placeholder('—'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                    ->label('Requested')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->label('Paid')
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
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawals::route('/'),
            'view'  => ViewWithdrawal::route('/{record}'),
        ];
    }
}
