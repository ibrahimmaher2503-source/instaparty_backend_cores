<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Pages;

use App\Modules\Catalog\Application\Actions\ImportRentalServicesFromExcelAction;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\UploadedFile;

class ImportRentalServicesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Services';

    protected static ?string $navigationLabel = 'Import Rental Services';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'catalog::filament.pages.import-rental-services';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?ExcelImport $lastImport = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('file')
                    ->label(__('catalog.import_file_label'))
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->required()
                    ->disk('local')
                    ->directory('excel-imports-temp'),
            ])
            ->statePath('data');
    }

    public function import(ImportRentalServicesFromExcelAction $action): void
    {
        $data = $this->form->getState();

        /** @var VendorProfile|null $vendor */
        $vendor = auth()->user()?->vendorProfile;

        if ($vendor === null) {
            Notification::make()
                ->title(__('catalog.import_no_vendor'))
                ->danger()
                ->send();

            return;
        }

        $path = storage_path('app/'.$data['file']);
        $file = new UploadedFile($path, basename($path), null, null, true);

        $this->lastImport = $action->execute($file, $vendor->id, app()->getLocale());

        if ($this->lastImport->status === 'completed') {
            Notification::make()
                ->title(__('catalog.import_success', ['count' => $this->lastImport->imported_rows]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('catalog.import_failed'))
                ->danger()
                ->send();
        }

        $this->form->fill();
    }
}
