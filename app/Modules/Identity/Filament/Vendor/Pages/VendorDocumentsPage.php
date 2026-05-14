<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Identity\Application\Actions\DeleteVendorDocumentAction;
use App\Modules\Identity\Application\Actions\UploadVendorDocumentAction;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorDocumentsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'profile';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'vendor-portal.pages.vendor-documents';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.documents.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.documents.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VendorDocument::query()
                    ->where('vendor_profile_id', $this->getVendorProfile()->id)
                    ->latest()
            )
            ->columns([
                TextColumn::make('doc_type')
                    ->label(__('vendor-portal.documents.type'))
                    ->badge()
                    ->formatStateUsing(fn (DocumentType $state) => match ($state) {
                        DocumentType::Cr => 'Commercial Register',
                        DocumentType::TaxCard => 'Tax Card',
                        DocumentType::NationalId => 'National ID',
                        DocumentType::IbanProof => 'IBAN Proof',
                        DocumentType::Other => 'Other',
                    }),
                TextColumn::make('status')
                    ->label(__('vendor-portal.documents.status'))
                    ->badge()
                    ->color(fn (DocumentStatus $state) => match ($state) {
                        DocumentStatus::Pending => 'warning',
                        DocumentStatus::Approved => 'success',
                        DocumentStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (DocumentStatus $state) => match ($state) {
                        DocumentStatus::Pending => __('vendor-portal.documents.pending'),
                        DocumentStatus::Approved => __('vendor-portal.documents.approved'),
                        DocumentStatus::Rejected => __('vendor-portal.documents.rejected'),
                    }),
                TextColumn::make('file_name')
                    ->label('File')
                    ->limit(30),
                TextColumn::make('review_notes')
                    ->label(__('vendor-portal.documents.review_notes'))
                    ->formatStateUsing(fn ($record) => $record->getTranslation('review_notes', app()->getLocale()) ?? '—')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->actions([
                TableAction::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VendorDocument $record) => $record->status === DocumentStatus::Rejected)
                    ->action(function (VendorDocument $record): void {
                        app(DeleteVendorDocumentAction::class)->execute(
                            $this->getVendorProfile(),
                            $record,
                        );
                        Notification::make()->title('Document deleted.')->success()->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label(__('vendor-portal.documents.upload'))
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Select::make('doc_type')
                        ->label(__('vendor-portal.documents.type'))
                        ->options([
                            DocumentType::Cr->value => 'Commercial Register',
                            DocumentType::TaxCard->value => 'Tax Card',
                            DocumentType::NationalId->value => 'National ID',
                            DocumentType::IbanProof->value => 'IBAN Proof',
                            DocumentType::Other->value => 'Other',
                        ])
                        ->required(),
                    FileUpload::make('file')
                        ->label('Document File')
                        ->required()
                        ->maxSize(10240)
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']),
                ])
                ->action(function (array $data): void {
                    $profile = $this->getVendorProfile();

                    // Block re-upload while a document of same type is pending
                    $pendingExists = VendorDocument::query()
                        ->where('vendor_profile_id', $profile->id)
                        ->where('doc_type', $data['doc_type'])
                        ->where('status', DocumentStatus::Pending->value)
                        ->exists();

                    if ($pendingExists) {
                        Notification::make()
                            ->title(__('vendor-portal.documents.cannot_reupload'))
                            ->danger()
                            ->send();

                        return;
                    }

                    app(UploadVendorDocumentAction::class)->execute(
                        $profile,
                        $data['file'],
                        DocumentType::from($data['doc_type']),
                    );

                    Notification::make()->title('Document uploaded successfully.')->success()->send();
                }),
        ];
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
