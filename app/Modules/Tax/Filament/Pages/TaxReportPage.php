<?php

declare(strict_types=1);

namespace App\Modules\Tax\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\DB;

class TaxReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'tax::filament.pages.tax-report';

    public ?array $data = [];

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.tax');
    }

    public static function getNavigationLabel(): string
    {
        return __('tax.report_page');
    }

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo   = now()->toDateString();
        $this->form->fill(['date_from' => $this->dateFrom, 'date_to' => $this->dateTo]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('date_from')
                    ->label(__('tax.date_from'))
                    ->required()
                    ->native(false),
                DatePicker::make('date_to')
                    ->label(__('tax.date_to'))
                    ->required()
                    ->native(false)
                    ->afterOrEqual('date_from'),
            ])
            ->statePath('data')
            ->columns(2);
    }

    public function getStats(): array
    {
        $from = $this->data['date_from'] ?? $this->dateFrom;
        $to   = $this->data['date_to']   ?? $this->dateTo;

        $rows = DB::table('bookings')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->whereIn('payment_status', ['paid', 'partially_refunded'])
            ->selectRaw('COUNT(*) as total_bookings, SUM(total_vat_minor) as total_vat, AVG(total_vat_minor) as avg_vat')
            ->first();

        return [
            'total_vat'      => (int) ($rows->total_vat ?? 0),
            'total_bookings' => (int) ($rows->total_bookings ?? 0),
            'avg_vat'        => (int) ($rows->avg_vat ?? 0),
        ];
    }

    public function apply(): void
    {
        $this->form->validate();
    }
}
