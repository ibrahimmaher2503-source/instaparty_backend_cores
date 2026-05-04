<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Application\Actions\GenerateDocumentSignedUrlAction;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VendorDocumentFilamentResource extends Resource
{
    protected static ?string $model = VendorDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_onboarding');
    }

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.documents');
    }

    public static function getModelLabel(): string
    {
        return __('identity.vendor_document');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.vendor_documents');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendorProfile.business_name')
                    ->label(__('identity.columns.vendor'))
                    ->getStateUsing(fn (VendorDocument $record): string => $record->vendorProfile?->getTranslation('business_name', app()->getLocale(), false) ?: '')
                    ->searchable(query: fn ($query, $search) => $query->whereHas('vendorProfile', fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(business_name, '$.en')) like ?", ["%{$search}%"]))),
                TextColumn::make('doc_type')
                    ->label(__('identity.columns.doc_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => __('identity.document_type.'.($state instanceof \BackedEnum ? $state->value : $state))),
                TextColumn::make('file_name')
                    ->label(__('identity.columns.file_name'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('identity.columns.status'))
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => __('identity.document_status.'.($state instanceof \BackedEnum ? $state->value : $state))),
                TextColumn::make('created_at')
                    ->label(__('identity.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('identity.columns.status'))
                    ->options([
                        'pending' => __('identity.document_status.pending'),
                        'approved' => __('identity.document_status.approved'),
                        'rejected' => __('identity.document_status.rejected'),
                    ]),
                SelectFilter::make('doc_type')
                    ->label(__('identity.columns.doc_type'))
                    ->options([
                        'cr' => __('identity.document_type.cr'),
                        'tax_card' => __('identity.document_type.tax_card'),
                        'national_id' => __('identity.document_type.national_id'),
                        'iban_proof' => __('identity.document_type.iban_proof'),
                        'other' => __('identity.document_type.other'),
                    ]),
            ])
            ->actions([
                Action::make('download')
                    ->label(__('identity.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (VendorDocument $record): void {
                        $url = app(GenerateDocumentSignedUrlAction::class)->execute($record);
                        Notification::make()
                            ->title(__('identity.notifications.signed_url_generated'))
                            ->body($url)
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorDocuments::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
