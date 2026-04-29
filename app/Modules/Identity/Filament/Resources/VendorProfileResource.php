<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\RevokeVendorTypeAction;
use App\Modules\Identity\Application\Actions\SuspendVendorAction;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages;
use App\Modules\Shared\Domain\Enums\ProductType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VendorProfileResource extends Resource
{
    protected static ?string $model = VendorProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.all_vendors');
    }

    public static function getModelLabel(): string
    {
        return __('identity.vendor_profile');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.vendor_profiles');
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
                    ->limit(40)
                    ->searchable(query: fn ($query, $search) => $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(business_name, '$.en')) like ?", ["%{$search}%"])),
                TextColumn::make('approval_status')
                    ->label(__('identity.columns.approval_status'))
                    ->badge()
                    ->color(fn (ApprovalStatus $state): string => match ($state) {
                        ApprovalStatus::Pending => 'warning',
                        ApprovalStatus::Approved => 'success',
                        ApprovalStatus::Rejected, ApprovalStatus::Suspended => 'danger',
                    })
                    ->formatStateUsing(fn (ApprovalStatus $state): string => __('identity.status.'.$state->value))
                    ->sortable(),
                TextColumn::make('business_type')
                    ->label(__('identity.columns.business_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('identity.business_type.'.$state)),
                TextColumn::make('created_at')
                    ->label(__('identity.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('approval_status')
                    ->label(__('identity.columns.approval_status'))
                    ->options([
                        'pending' => __('identity.status.pending'),
                        'approved' => __('identity.status.approved'),
                        'rejected' => __('identity.status.rejected'),
                        'suspended' => __('identity.status.suspended'),
                    ]),
            ])
            ->actions([
                Action::make('approveForType')
                    ->label(__('identity.actions.approve_for_type'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (VendorProfile $record) => $record->approval_status === ApprovalStatus::Approved && auth()->user()?->can('approve_vendor_for_type'))
                    ->form([
                        Select::make('product_type')
                            ->label(__('identity.fields.product_type'))
                            ->options([
                                'rental' => __('identity.product_type.rental'),
                                'sale' => __('identity.product_type.sale'),
                                'digital' => __('identity.product_type.digital'),
                            ])
                            ->required(),
                    ])
                    ->action(function (VendorProfile $record, array $data): void {
                        app(ApproveVendorForTypeAction::class)->execute($record, ProductType::from($data['product_type']));
                        Notification::make()
                            ->title(__('identity.notifications.type_approved', [
                                'type' => __('identity.product_type.'.$data['product_type']),
                            ]))
                            ->success()
                            ->send();
                    }),

                Action::make('revokeType')
                    ->label(__('identity.actions.revoke_type'))
                    ->icon('heroicon-o-minus-circle')
                    ->color('warning')
                    ->visible(fn (VendorProfile $record) => $record->approvedTypes()->exists() && auth()->user()?->can('revoke_vendor_type'))
                    ->form([
                        Select::make('product_type')
                            ->label(__('identity.fields.product_type'))
                            ->options([
                                'rental' => __('identity.product_type.rental'),
                                'sale' => __('identity.product_type.sale'),
                                'digital' => __('identity.product_type.digital'),
                            ])
                            ->required(),
                        Textarea::make('revoke_reason_en')
                            ->label(__('identity.forms.revoke_reason_en'))
                            ->maxLength(1000),
                        Textarea::make('revoke_reason_ar')
                            ->label(__('identity.forms.revoke_reason_ar'))
                            ->maxLength(1000),
                    ])
                    ->action(function (VendorProfile $record, array $data): void {
                        $reason = array_filter([
                            'en' => $data['revoke_reason_en'] ?? null,
                            'ar' => $data['revoke_reason_ar'] ?? null,
                        ]);
                        app(RevokeVendorTypeAction::class)->execute($record, ProductType::from($data['product_type']), $reason);
                        Notification::make()->title(__('identity.notifications.type_revoked'))->warning()->send();
                    }),

                Action::make('suspend')
                    ->label(__('identity.actions.suspend'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VendorProfile $record) => $record->approval_status !== ApprovalStatus::Suspended && auth()->user()?->can('suspend_vendor'))
                    ->action(function (VendorProfile $record): void {
                        app(SuspendVendorAction::class)->execute($record);
                        Notification::make()->title(__('identity.notifications.vendor_suspended'))->danger()->send();
                    }),

                Tables\Actions\ViewAction::make(),
            ]);
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
                    TextEntry::make('business_type')
                        ->label(__('identity.columns.business_type'))
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => __('identity.business_type.'.$state)),
                    TextEntry::make('approval_status')
                        ->label(__('identity.columns.approval_status'))
                        ->badge()
                        ->color(fn (ApprovalStatus $state): string => match ($state) {
                            ApprovalStatus::Pending => 'warning',
                            ApprovalStatus::Approved => 'success',
                            ApprovalStatus::Rejected, ApprovalStatus::Suspended => 'danger',
                        })
                        ->formatStateUsing(fn (ApprovalStatus $state): string => __('identity.status.'.$state->value)),
                ]),
            InfolistSection::make(__('identity.sections.business_profile'))
                ->columns(2)
                ->schema([
                    TextEntry::make('business_name')
                        ->label(__('identity.forms.business_name_en'))
                        ->getStateUsing(fn (VendorProfile $record): ?string => $record->getTranslation('business_name', 'en', false) ?: null),
                    TextEntry::make('business_name_ar')
                        ->label(__('identity.forms.business_name_ar'))
                        ->getStateUsing(fn (VendorProfile $record): ?string => $record->getTranslation('business_name', 'ar', false) ?: null),
                    TextEntry::make('slug')->label(__('identity.columns.slug')),
                    TextEntry::make('primary_governorate_id')->label(__('identity.columns.governorate')),
                    TextEntry::make('primary_city_id')->label(__('identity.columns.city')),
                    TextEntry::make('created_at')->label(__('identity.columns.created_at'))->dateTime(),
                ]),
            InfolistSection::make(__('identity.sections.approved_product_types'))
                ->schema([
                    TextEntry::make('approved_product_types')
                        ->label(__('identity.columns.active_type_approvals'))
                        ->getStateUsing(function (VendorProfile $record): string {
                            $types = $record->approvedTypes()
                                ->whereNull('revoked_at')
                                ->pluck('product_type')
                                ->map(fn ($t) => $t instanceof ProductType ? $t->value : $t)
                                ->map(fn (string $t): string => __('identity.product_type.'.$t))
                                ->all();

                            return $types ? implode('، ', $types) : __('identity.placeholders.none');
                        }),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorProfiles::route('/'),
            'view' => Pages\ViewVendorProfile::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
