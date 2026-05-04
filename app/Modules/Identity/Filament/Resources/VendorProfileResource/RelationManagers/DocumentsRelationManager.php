<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers;

use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('identity.sections.documents');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->badge()
                    ->label(__('identity.document_type')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state->value ?? $state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->label(__('identity.document_status')),
                Tables\Columns\TextColumn::make('file_path')
                    ->label(__('identity.document_file'))
                    ->limit(40),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('shared.updated_at')),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Action::make('reUpload')
                    ->label(__('identity.actions.re_upload_document'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->can('re_upload_vendor_document'))
                    ->form([
                        FileUpload::make('file')
                            ->label(__('identity.document_file'))
                            ->required()
                            ->disk('s3')
                            ->directory('vendor-documents')
                            ->maxSize(10240),
                    ])
                    ->action(function ($record, array $data): void {
                        $record->update([
                            'file_path' => $data['file'],
                            'status' => 'pending',
                        ]);

                        activity()
                            ->on($record)
                            ->causedBy(auth()->user())
                            ->withProperties(['document_type' => $record->document_type])
                            ->log('admin_re_uploaded_document');

                        Notification::make()
                            ->title(__('identity.notifications.document_uploaded'))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }
}
