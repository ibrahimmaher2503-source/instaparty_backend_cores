<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Pages;

use App\Modules\Catalog\Application\Actions\ImportDigitalServicesFromExcelAction;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VendorImportDigitalServicesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'services';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'vendor-portal.pages.vendor-import-services';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?ExcelImport $lastImport = null;

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.imports.import_digital');
    }

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.imports.import_digital');
    }

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

    public function import(ImportDigitalServicesFromExcelAction $action): void
    {
        $data = $this->form->getState();
        $vendor = auth()->user()?->vendorProfile;

        if ($vendor === null) {
            Notification::make()->title(__('catalog.import_no_vendor'))->danger()->send();

            return;
        }

        $absolutePath = Storage::disk('local')->path($data['file']);
        $file = new UploadedFile($absolutePath, basename($absolutePath), null, null, true);

        $this->lastImport = $action->execute($file, $vendor->id, app()->getLocale());

        if ($this->lastImport->status === 'completed') {
            Notification::make()
                ->title(__('catalog.import_success', ['count' => $this->lastImport->imported_rows]))
                ->success()
                ->send();
        } else {
            Notification::make()->title(__('catalog.import_failed'))->danger()->send();
        }

        $this->form->fill();
    }
}
