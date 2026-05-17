<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\GenerateDocumentSignedUrlAction;
use App\Modules\Identity\Application\Actions\RejectVendorProfileAction;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class VendorApprovalQueueResource extends Resource
{
    protected static ?string $model = VendorProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $slug = 'vendor-approval-queue';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.queue');
    }

    public static function getModelLabel(): string
    {
        return __('identity.vendor_profile');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.vendor_profiles');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->pending()->withTrashed(false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('identity.columns.id'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('business_name')
                    ->label(__('identity.columns.business_name'))
                    ->getStateUsing(fn (VendorProfile $record): string => $record->getTranslation('business_name', app()->getLocale(), false) ?: '')
                    ->searchable(query: fn ($query, $search) => $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(business_name, '$.en')) like ?", ["%{$search}%"]))
                    ->limit(40),
                TextColumn::make('business_type')
                    ->label(__('identity.columns.business_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('identity.business_type.'.$state)),
                TextColumn::make('user.email')
                    ->label(__('identity.columns.email')),
                TextColumn::make('documents_count')
                    ->label(__('identity.columns.docs'))
                    ->counts('documents')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('identity.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('identity.actions.review'))
                    ->icon('heroicon-o-eye')
                    ->color('primary'),

                Action::make('approve')
                    ->label(__('identity.actions.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()?->can('approve_vendor_profile'))
                    ->action(function (VendorProfile $record): void {
                        app(ApproveVendorProfileAction::class)->execute($record);
                        Notification::make()->title(__('identity.notifications.vendor_approved'))->success()->send();
                    }),

                Action::make('reject')
                    ->label(__('identity.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => auth()->user()?->can('approve_vendor_profile'))
                    ->form([
                        Textarea::make('rejection_reason_en')
                            ->label(__('identity.forms.rejection_reason_en'))
                            ->required()
                            ->maxLength(1000),
                        Textarea::make('rejection_reason_ar')
                            ->label(__('identity.forms.rejection_reason_ar'))
                            ->maxLength(1000),
                    ])
                    ->action(function (VendorProfile $record, array $data): void {
                        $reason = array_filter([
                            'en' => $data['rejection_reason_en'] ?? null,
                            'ar' => $data['rejection_reason_ar'] ?? null,
                        ]);
                        app(RejectVendorProfileAction::class)->execute($record, $reason);
                        Notification::make()->title(__('identity.notifications.vendor_rejected'))->danger()->send();
                    }),
            ])
            ->emptyStateHeading(__('admin.empty_states.no_pending_vendors'))
            ->emptyStateDescription(__('admin.empty_states.no_pending_vendors_description'));
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make(__('identity.sections.identity'))
                ->columns(2)
                ->schema([
                    TextEntry::make('public_id')->label(__('identity.columns.id'))->copyable(),
                    TextEntry::make('user.email')->label(__('identity.columns.email')),
                    TextEntry::make('user.phone_e164')->label(__('identity.columns.phone')),
                    TextEntry::make('user.phone_verified_at')
                        ->label(__('identity.columns.phone_verified'))
                        ->dateTime()
                        ->placeholder(__('identity.placeholders.not_verified')),
                    TextEntry::make('business_type')
                        ->label(__('identity.columns.business_type'))
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => __('identity.business_type.'.$state)),
                    TextEntry::make('approval_status')
                        ->label(__('identity.columns.approval_status'))
                        ->badge()
                        ->color('warning')
                        ->formatStateUsing(fn ($state): string => __('identity.status.'.(is_object($state) ? $state->value : $state))),
                ]),

            InfolistSection::make(__('identity.sections.business_profile'))
                ->columns(2)
                ->schema([
                    TextEntry::make('business_name_en')
                        ->label(__('identity.forms.business_name_en'))
                        ->getStateUsing(fn (VendorProfile $record): ?string => $record->getTranslation('business_name', 'en', false) ?: null),
                    TextEntry::make('business_name_ar')
                        ->label(__('identity.forms.business_name_ar'))
                        ->getStateUsing(fn (VendorProfile $record): ?string => $record->getTranslation('business_name', 'ar', false) ?: null),
                    TextEntry::make('slug')->label(__('identity.columns.slug')),
                    TextEntry::make('primaryGovernorate.name')
                        ->label(__('identity.columns.governorate'))
                        ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—')),
                    TextEntry::make('primaryCity.name')
                        ->label(__('identity.columns.city'))
                        ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—')),
                    TextEntry::make('created_at')->label(__('identity.columns.created_at'))->dateTime(),
                    TextEntry::make('bio_en')
                        ->label(__('identity.forms.bio_en'))
                        ->getStateUsing(fn (VendorProfile $record): ?string => $record->getTranslation('bio', 'en', false) ?: null)
                        ->placeholder(__('identity.placeholders.dash'))
                        ->columnSpanFull(),
                ]),

            InfolistSection::make(__('identity.sections.banking'))
                ->columns(2)
                ->schema([
                    TextEntry::make('bank_name')->label(__('identity.columns.bank_name'))->placeholder(__('identity.placeholders.dash')),
                    TextEntry::make('bank_account_holder')->label(__('identity.columns.bank_account_holder'))->placeholder(__('identity.placeholders.dash')),
                    TextEntry::make('bank_iban')->label(__('identity.columns.bank_iban'))->placeholder(__('identity.placeholders.dash'))->copyable(),
                    TextEntry::make('bank_swift_bic')->label(__('identity.columns.bank_swift_bic'))->placeholder(__('identity.placeholders.dash')),
                ]),

            InfolistSection::make(__('identity.sections.uploaded_documents'))
                ->schema([
                    RepeatableEntry::make('documents')
                        ->columns(4)
                        ->schema([
                            TextEntry::make('doc_type')
                                ->label(__('identity.columns.doc_type'))
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => __('identity.document_type.'.$state)),
                            TextEntry::make('file_name')->label(__('identity.columns.file_name')),
                            TextEntry::make('status')
                                ->label(__('identity.columns.status'))
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => __('identity.document_status.'.$state)),
                            TextEntry::make('signed_url')
                                ->label(__('identity.columns.download'))
                                ->getStateUsing(function (VendorDocument $record): string {
                                    try {
                                        return app(GenerateDocumentSignedUrlAction::class)->execute($record);
                                    } catch (Throwable) {
                                        return '#';
                                    }
                                })
                                ->url(fn (string $state): string => $state, shouldOpenInNewTab: true)
                                ->formatStateUsing(fn (string $state): string => $state === '#' ? __('identity.placeholders.s3_not_configured') : __('identity.placeholders.open_document')),
                        ])
                        ->placeholder(__('identity.placeholders.no_documents')),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorApprovalQueue::route('/'),
            'view' => Pages\ReviewVendorApplication::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
