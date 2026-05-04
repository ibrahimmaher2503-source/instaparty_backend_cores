<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\ImpersonateVendorAction;
use App\Modules\Identity\Application\Actions\RevokeVendorTypeAction;
use App\Modules\Identity\Application\Actions\SuspendVendorAction;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Enums\DayOfWeek;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class VendorProfileResource extends Resource
{
    protected static ?string $model = VendorProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('identity.nav.vendor_management');
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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Translations')
                ->tabs([
                    Tabs\Tab::make('English')
                        ->schema([
                            TextInput::make('business_name.en')
                                ->label(__('identity.forms.business_name_en'))
                                ->required()
                                ->maxLength(255),
                            Textarea::make('bio.en')
                                ->label(__('identity.forms.bio_en'))
                                ->rows(3)
                                ->maxLength(2000),
                            Textarea::make('address_line.en')
                                ->label(__('identity.fields.address_line').' (EN)')
                                ->rows(2)
                                ->maxLength(500),
                        ]),
                    Tabs\Tab::make('العربية')
                        ->schema([
                            TextInput::make('business_name.ar')
                                ->label(__('identity.forms.business_name_ar'))
                                ->required()
                                ->maxLength(255)
                                ->extraInputAttributes(['dir' => 'rtl']),
                            Textarea::make('bio.ar')
                                ->label(__('identity.forms.bio_ar'))
                                ->rows(3)
                                ->maxLength(2000)
                                ->extraInputAttributes(['dir' => 'rtl']),
                            Textarea::make('address_line.ar')
                                ->label(__('identity.fields.address_line').' (AR)')
                                ->rows(2)
                                ->maxLength(500)
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make(__('identity.sections.business_profile'))
                ->columns(2)
                ->schema([
                    Select::make('business_type')
                        ->label(__('identity.fields.business_type'))
                        ->options([
                            BusinessType::Individual->value => __('identity.business_type.individual'),
                            BusinessType::Company->value => __('identity.business_type.company'),
                        ])
                        ->required(),
                    TextInput::make('commercial_register_no')
                        ->label('Commercial Register No.')
                        ->maxLength(60),
                    TextInput::make('tax_id')
                        ->label('Tax ID')
                        ->maxLength(60),
                    TextInput::make('national_id')
                        ->label('National ID')
                        ->maxLength(20),
                    Select::make('primary_governorate_id')
                        ->label(__('identity.columns.governorate'))
                        ->options(fn () => DB::table('governorates')->pluck('name', 'id')
                            ->mapWithKeys(fn ($name, $id) => [$id => is_string($name) ? (json_decode($name, true)['en'] ?? $name) : $name])
                            ->toArray())
                        ->searchable()
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn (callable $set) => $set('primary_city_id', null)),
                    Select::make('primary_city_id')
                        ->label(__('identity.columns.city'))
                        ->options(fn (callable $get) => DB::table('cities')
                            ->when($get('primary_governorate_id'), fn ($q, $gov) => $q->where('governorate_id', $gov))
                            ->get()
                            ->mapWithKeys(fn ($city) => [
                                $city->id => is_string($city->name) ? (json_decode($city->name, true)['en'] ?? $city->name) : $city->name,
                            ])
                            ->toArray())
                        ->searchable()
                        ->required(),
                ]),

            Section::make(__('identity.sections.banking'))
                ->columns(2)
                ->schema([
                    TextInput::make('bank_holder_name')
                        ->label(__('identity.columns.bank_account_holder'))
                        ->maxLength(160),
                    TextInput::make('bank_iban')
                        ->label(__('identity.columns.bank_iban'))
                        ->maxLength(40),
                    TextInput::make('bank_swift')
                        ->label(__('identity.columns.bank_swift_bic'))
                        ->maxLength(20),
                    TextInput::make('bank_name')
                        ->label(__('identity.columns.bank_name'))
                        ->maxLength(120),
                ]),

            Section::make(__('identity.sections.business_hours'))
                ->schema([
                    Repeater::make('business_hours')
                        ->label('')
                        ->schema([
                            Select::make('day_of_week')
                                ->label(__('identity.columns.day_of_week'))
                                ->options(array_map(
                                    fn (DayOfWeek $d) => $d->label(),
                                    DayOfWeek::cases(),
                                ))
                                ->required(),
                            TimePicker::make('opens_at')
                                ->label(__('identity.columns.opens_at'))
                                ->seconds(false)
                                ->nullable(),
                            TimePicker::make('closes_at')
                                ->label(__('identity.columns.closes_at'))
                                ->seconds(false)
                                ->nullable(),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->collapsible(),
                ])
                ->collapsible(),

            Section::make(__('identity.sections.coverage_areas'))
                ->schema([
                    Repeater::make('coverage_areas')
                        ->label('')
                        ->schema([
                            Select::make('city_id')
                                ->label(__('identity.columns.city'))
                                ->options(fn () => DB::table('cities')
                                    ->get()
                                    ->mapWithKeys(fn ($city) => [
                                        $city->id => is_string($city->name)
                                            ? (json_decode($city->name, true)['en'] ?? $city->name)
                                            : $city->name,
                                    ])
                                    ->toArray())
                                ->searchable()
                                ->required(),
                            TextInput::make('delivery_fee_minor')
                                ->label(__('identity.columns.delivery_fee'))
                                ->numeric()
                                ->suffix('piastres')
                                ->default(0),
                            TextInput::make('min_order_minor')
                                ->label(__('identity.columns.min_order'))
                                ->numeric()
                                ->suffix('piastres')
                                ->default(0),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->collapsible(),
                ])
                ->collapsible(),
        ]);
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
                    ->formatStateUsing(fn ($state): string => __('identity.business_type.'.($state instanceof \BackedEnum ? $state->value : $state))),
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
                SelectFilter::make('primary_governorate_id')
                    ->label(__('identity.columns.governorate'))
                    ->options(fn () => DB::table('governorates')
                        ->get()
                        ->mapWithKeys(fn ($gov) => [
                            $gov->id => is_string($gov->name)
                                ? (json_decode($gov->name, true)['en'] ?? $gov->name)
                                : $gov->name,
                        ])
                        ->toArray())
                    ->searchable(),
                Filter::make('has_type_approval')
                    ->label('Approved Product Type')
                    ->form([
                        Select::make('product_type')
                            ->label(__('identity.columns.product_type'))
                            ->options([
                                'rental' => __('identity.product_type.rental'),
                                'sale' => __('identity.product_type.sale'),
                                'digital' => __('identity.product_type.digital'),
                            ]),
                    ])
                    ->query(function ($query, array $data): void {
                        if (! empty($data['product_type'])) {
                            $query->whereHas('approvedTypes', fn ($q) => $q->where('product_type', $data['product_type']));
                        }
                    }),
            ])
            ->actions([
                Action::make('impersonate')
                    ->label(__('identity.actions.impersonate'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(__('identity.confirmations.impersonate_warning'))
                    ->visible(fn () => auth()->user()?->can('impersonate_vendor'))
                    ->action(function (VendorProfile $record): void {
                        $token = app(ImpersonateVendorAction::class)->execute($record, auth()->user());
                        Notification::make()
                            ->title(__('identity.notifications.impersonation_token'))
                            ->body($token)
                            ->info()
                            ->persistent()
                            ->send();
                    }),

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
                            ->required()
                            ->maxLength(1000),
                        Textarea::make('revoke_reason_ar')
                            ->label(__('identity.forms.revoke_reason_ar'))
                            ->required()
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

                Tables\Actions\EditAction::make(),
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
                        ->formatStateUsing(fn ($state): string => __('identity.business_type.'.($state instanceof \BackedEnum ? $state->value : $state))),
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

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\ServicesRelationManager::class,
            RelationManagers\BookingVendorsRelationManager::class,
            RelationManagers\WalletRelationManager::class,
            RelationManagers\WithdrawalsRelationManager::class,
            RelationManagers\VendorReviewsRelationManager::class,
            RelationManagers\ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorProfiles::route('/'),
            'edit' => Pages\EditVendorProfile::route('/{record}/edit'),
            'view' => Pages\ViewVendorProfile::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
