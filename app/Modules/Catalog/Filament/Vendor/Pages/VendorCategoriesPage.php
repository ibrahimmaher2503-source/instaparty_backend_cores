<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Pages;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class VendorCategoriesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'services';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'vendor-portal.pages.vendor-categories';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.services.title');
    }

    public static function getNavigationLabel(): string
    {
        return 'Categories';
    }

    public function table(Table $table): Table
    {
        $profile = $this->getVendorProfile();
        $approvedTypes = $profile->approvedTypes
            ->pluck('product_type')
            ->map(fn ($t) => $t->value)
            ->toArray();

        return $table
            ->query(
                Category::query()
                    ->where('is_active', true)
                    ->when(count($approvedTypes) > 0, function (Builder $query) use ($approvedTypes): void {
                        $query->where(function (Builder $q) use ($approvedTypes): void {
                            foreach ($approvedTypes as $type) {
                                $q->orWhereJsonContains('allowed_product_types', $type);
                            }
                        });
                    })
                    ->orderBy('sort_order')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Category')
                    ->formatStateUsing(fn (Category $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(),
                TextColumn::make('allowed_product_types')
                    ->label('Product Types')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? implode(', ', array_map('ucfirst', $state))
                        : ucfirst($state)
                    ),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->formatStateUsing(fn ($record) => $record->parent
                        ? $record->parent->getTranslation('name', app()->getLocale())
                        : '—'
                    )
                    ->placeholder('Root'),
            ])
            ->defaultSort('sort_order');
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
