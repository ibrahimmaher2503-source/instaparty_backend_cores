<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources;

use App\Modules\Subscriptions\Application\Actions\ApplyAdminTierOverrideAction;
use App\Modules\Subscriptions\Application\Actions\RevokeAdminTierOverrideAction;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Filament\Resources\VendorSubscriptionResource\Pages;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorSubscriptionResource extends Resource
{
    protected static ?string $model = VendorSubscription::class;

    protected static ?string $navigationGroup = 'subscriptions';

    protected static ?string $navigationLabel = 'Vendor Subscriptions';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('public_id')
                    ->disabled(),

                Forms\Components\TextInput::make('vendorProfile.business_name')
                    ->label('Vendor')
                    ->disabled(),

                Forms\Components\TextInput::make('plan.plan_code')
                    ->label('Plan')
                    ->disabled(),

                Forms\Components\TextInput::make('status')
                    ->disabled(),

                Forms\Components\Toggle::make('is_admin_override')
                    ->label('Admin Override')
                    ->disabled(),

                Forms\Components\DateTimePicker::make('started_at')
                    ->disabled(),

                Forms\Components\DateTimePicker::make('current_period_end')
                    ->label('Expires At')
                    ->disabled(),

                Forms\Components\DateTimePicker::make('override_expires_at')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendorProfile.business_name')
                    ->label(__('subscription.vendor'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('plan.plan_code')
                    ->label('Plan')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'free' => 'gray',
                        'silver' => 'info',
                        'gold' => 'warning',
                        'premium' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state->value ?? $state) {
                        SubscriptionStatus::Active->value, SubscriptionStatus::Active => 'success',
                        SubscriptionStatus::PastDue->value, SubscriptionStatus::PastDue => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_admin_override')
                    ->label('Override?')
                    ->boolean(),

                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_period_end')
                    ->label(__('subscription.expires_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('override_expires_at')
                    ->label(__('subscription.override_expires_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(SubscriptionStatus::class),

                Tables\Filters\TernaryFilter::make('is_admin_override')
                    ->label('Admin Override Only'),

                Tables\Filters\SelectFilter::make('plan')
                    ->relationship('plan', 'plan_code'),
            ])
            ->actions([
                Tables\Actions\Action::make('overrideTier')
                    ->label(__('subscription.override_tier'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('plan_id')
                            ->label('Plan')
                            ->options(
                                SubscriptionPlan::query()
                                    ->where('is_published', true)
                                    ->pluck('name->en', 'id')
                            )
                            ->required(),

                        Forms\Components\TextInput::make('reason_en')
                            ->label(__('subscription.override_reason_en'))
                            ->required()
                            ->maxLength(500),

                        Forms\Components\TextInput::make('reason_ar')
                            ->label(__('subscription.override_reason_ar'))
                            ->required()
                            ->maxLength(500)
                            ->extraInputAttributes(['dir' => 'rtl']),

                        Forms\Components\DateTimePicker::make('override_expires_at')
                            ->label(__('subscription.override_expires_at'))
                            ->native(false)
                            ->minDate(now()->addDay()),
                    ])
                    ->action(function (VendorSubscription $record, array $data): void {
                        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
                        $expiresAt = isset($data['override_expires_at']) ? Carbon::parse($data['override_expires_at']) : null;

                        app(ApplyAdminTierOverrideAction::class)->execute(
                            $record->vendor_profile_id,
                            $plan,
                            $data['reason_en'],
                            $data['reason_ar'],
                            $expiresAt,
                        );

                        Notification::make()
                            ->title(__('subscription.override_tier').' Applied')
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()->can('override_vendor_subscription')),

                Tables\Actions\Action::make('revokeOverride')
                    ->label(__('subscription.revoke_override'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VendorSubscription $record): void {
                        app(RevokeAdminTierOverrideAction::class)->execute($record);

                        Notification::make()
                            ->title(__('subscription.revoke_override').' Completed')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (VendorSubscription $record) => $record->is_admin_override && ! $record->status->isTerminal()),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorSubscriptions::route('/'),
        ];
    }

    public static function getHeaderActions(): array
    {
        return [];
    }
}
