---
description: Filament v3 UI component reference — use these exact namespaces and patterns
globs:
  - "app/Modules/*/Filament/**/*.php"
  - "app/Filament/**/*.php"
  - "resources/views/filament/**/*.blade.php"
---

# Filament v3 — UI Component Reference

> Authoritative reference for components, namespaces, and patterns Claude must use when generating Filament Resources, Pages, Widgets, and custom UI for InstaParty.
>
> **Plugin compatibility:** assumes the plugins listed in `docs/specs/10_Package_List.md` §3 are installed. Don't import a plugin component without checking it's in that list.

---

## 1. Forms (`Filament\Forms\Components`)

### Inputs

| Component | Use when | Notable methods |
|---|---|---|
| `TextInput` | Single-line text, numbers, email | `->email()`, `->numeric()`, `->password()`, `->prefix()`, `->suffix()`, `->mask()`, `->live()` |
| `Textarea` | Multi-line plain text | `->rows()`, `->cols()`, `->autosize()` |
| `RichEditor` | Rich text without markdown | `->toolbarButtons([...])`, `->disableToolbarButtons([...])` |
| `MarkdownEditor` | Markdown content with preview | `->toolbarButtons([...])`, `->fileAttachmentsDisk()` |
| `Select` | Dropdown selection | `->options()`, `->relationship()`, `->searchable()`, `->preload()`, `->multiple()`, `->createOptionForm()` |
| `Toggle` | Boolean switch | `->onIcon()`, `->offIcon()`, `->onColor()`, `->offColor()` |
| `Checkbox` | Standalone boolean | `->inline()` |
| `CheckboxList` | Multi-select checkboxes | `->options()`, `->bulkToggleable()`, `->columns()` |
| `Radio` | Mutually exclusive options | `->options()`, `->inline()`, `->descriptions()` |
| `DatePicker` | Date only | `->displayFormat()`, `->minDate()`, `->maxDate()`, `->native(false)` |
| `DateTimePicker` | Date + time | Same as DatePicker, plus `->seconds(false)` |
| `TimePicker` | Time only | `->seconds(false)` |
| `FileUpload` | Generic file upload | `->disk('s3')`, `->directory('uploads')`, `->maxSize(10240)`, `->multiple()`, `->image()` |
| `ColorPicker` | Color selection | `->rgb()`, `->hex()`, `->hsl()` |
| `KeyValue` | Editable key-value pairs (e.g., custom_attributes) | `->keyLabel()`, `->valueLabel()`, `->reorderable()` |
| `Repeater` | Repeating field group | `->schema([...])`, `->minItems()`, `->maxItems()`, `->collapsed()`, `->reorderable()` |
| `Builder` | Block-based content composer | `->blocks([Block::make('...')->schema([...])])` |
| `TagsInput` | Free-form tags | `->separator(',')`, `->suggestions([...])` |
| `Hidden` | Non-visible field | Use sparingly; prefer `->default()` on a real field |

### Layout (organize fields, no data binding)

| Component | Use when |
|---|---|
| `Section` | Group of related fields with optional heading + description |
| `Tabs` | Multiple tabs (e.g., EN / AR translatable tabs, settings groups) |
| `Wizard` | Multi-step form (vendor onboarding, complex booking creation) |
| `Grid` | Multi-column layout (e.g., 2 columns: prices left, dimensions right) |
| `Group` | Logical grouping without visual chrome |
| `Fieldset` | Bordered group with legend |
| `Placeholder` | Static content (computed values, helper text, image preview) |
| `Split` | Two-pane layout (form on left, preview on right) |
| `Actions` | Inline action buttons within a form |
| `ViewField` | Render custom Blade view inside a form |

### Per-product-type form pattern

Each of `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` uses this skeleton:

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Tabs::make('Translations')
            ->tabs([
                Tabs\Tab::make('English')
                    ->schema(static::translatableFields('en')),
                Tabs\Tab::make('العربية')
                    ->schema(static::translatableFields('ar')),
            ])
            ->columnSpanFull(),

        Section::make(__('catalog.shared'))
            ->schema(static::sharedFields())
            ->columns(2),

        Section::make(__('catalog.type_specific'))
            ->schema(static::typeSpecificFields())   // varies per type
            ->columns(2),

        Section::make(__('catalog.media'))
            ->schema([
                SpatieMediaLibraryFileUpload::make('gallery')
                    ->collection('gallery')
                    ->multiple()
                    ->maxFiles(11)
                    ->image()
                    ->reorderable(),
            ]),
    ]);
}
```

`typeSpecificFields()` returns:
- For Rental: `requires_electricity`, `requires_outdoor_space`, `default_rental_duration_hours`, `setup_time_minutes`, `security_deposit_minor`, etc.
- For Sale: `is_perishable`, `is_made_to_order`, `lead_time_hours`, `stock_quantity`, `customization_fields`, etc.
- For Digital: `delivery_method`, `has_expiry`, `expiry_days_after_purchase`, `is_refundable_after_delivery`, `redemption_url_template`, etc.

---

## 2. Tables (`Filament\Tables\Columns`, `Filament\Tables\Filters`, `Filament\Tables\Actions`)

### Columns

| Component | Use when | Notable methods |
|---|---|---|
| `TextColumn` | Default for any text/number/date | `->searchable()`, `->sortable()`, `->badge()`, `->color()`, `->icon()`, `->money('EGP', divideBy: 100)`, `->dateTime()`, `->limit()`, `->wrap()` |
| `IconColumn` | Boolean as icon, status icons | `->boolean()`, `->options([...])`, `->colors([...])` |
| `ImageColumn` | Display thumbnail | `->circular()`, `->square()`, `->stacked()` |
| `ToggleColumn` | Inline editable toggle (use sparingly; prefer Edit form) | `->beforeStateUpdated()`, `->afterStateUpdated()` |
| `CheckboxColumn` | Inline editable checkbox | Same hooks |
| `SelectColumn` | Inline dropdown | `->options()` |
| `TextInputColumn` | Inline editable text | Same hooks |
| `ColorColumn` | Show color swatch | `->copyable()` |

### Money column convention

```php
TextColumn::make('total_minor')
    ->money('EGP', divideBy: 100)   // 100 piastres = 1 EGP
    ->sortable()
    ->label(__('booking.total')),
```

### Product type badge column convention

```php
TextColumn::make('product_type')
    ->badge()
    ->color(fn (ProductType $state): string => match ($state) {
        ProductType::Rental  => 'warning',
        ProductType::Sale    => 'success',
        ProductType::Digital => 'info',
    })
    ->formatStateUsing(fn (ProductType $state) => $state->label())
    ->label(__('catalog.product_type')),
```

### Layout columns (compact mobile-friendly)

| Component | Use when |
|---|---|
| `Layout\Stack` | Stack columns vertically |
| `Layout\Split` | Side-by-side columns |
| `Layout\Panel` | Card-style row |
| `Layout\Grid` | Multi-column grid within a row |

### Filters

| Component | Use when |
|---|---|
| `SelectFilter` | Dropdown filter (e.g., filter services by type) |
| `TernaryFilter` | Yes / No / All filter (e.g., is_published) |
| `Filter` | Custom filter with arbitrary form schema |
| `TrashedFilter` | Show trashed/active/all (for soft-deleting models) |
| `QueryBuilder` | Advanced multi-criteria filter (use for complex monitor pages) |

### Filter convention for Service Resources

Always include a `product_type` filter:

```php
->filters([
    SelectFilter::make('product_type')
        ->options(ProductType::class)
        ->label(__('catalog.product_type')),
    SelectFilter::make('status')
        ->options([
            'draft'          => __('catalog.status.draft'),
            'pending_review' => __('catalog.status.pending_review'),
            'published'      => __('catalog.status.published'),
            'archived'       => __('catalog.status.archived'),
        ]),
    SelectFilter::make('vendor_id')
        ->relationship('vendor', 'display_name->en')   // searchable by name
        ->searchable()
        ->preload(),
])
```

### Actions (per-row, header, bulk)

| Component | Use when |
|---|---|
| `Action` (table) | Custom row action (e.g., "Approve", "Moderate") |
| `BulkAction` | Action on selected rows |
| `CreateAction` | Built-in create — only on header |
| `EditAction`, `DeleteAction`, `ViewAction` | Built-in CRUD row actions |
| `ReplicateAction` | Duplicate a record |
| `ForceDeleteAction`, `RestoreAction` | For soft-delete tables |

### Custom action convention

```php
->actions([
    Action::make('approveType')
        ->label(__('vendor.approve_for_type'))
        ->icon('heroicon-o-check-badge')
        ->color('success')
        ->form([
            Select::make('product_type')
                ->options(ProductType::class)
                ->required(),
        ])
        ->action(function (VendorProfile $record, array $data) {
            app(ApproveVendorForTypeAction::class)
                ->execute($record, ProductType::from($data['product_type']));

            Notification::make()
                ->title(__('vendor.approved_successfully'))
                ->success()
                ->send();
        })
        ->requiresConfirmation()
        ->visible(fn (VendorProfile $record): bool =>
            auth()->user()->can('approve_vendor_profile')
        ),
    EditAction::make(),
    DeleteAction::make(),
])
```

---

## 3. Infolists (`Filament\Infolists\Components`)

Use Infolists for **read-only detail pages**, not for editable forms.

| Component | Use when |
|---|---|
| `TextEntry` | Default for any value | `->badge()`, `->copyable()`, `->money('EGP')` |
| `ImageEntry` | Show image | `->circular()`, `->stacked()` |
| `IconEntry` | Boolean / status icon | `->boolean()`, `->options()` |
| `ColorEntry` | Color swatch | `->copyable()` |
| `KeyValueEntry` | Show key-value object | |
| `RepeatableEntry` | Show array of repeating data | `->schema([...])` |
| `Section`, `Tabs`, `Grid`, `Group` | Layout (same as forms) | |

Use Infolists in `viewRecord` actions and on custom dashboard pages.

---

## 4. Notifications (`Filament\Notifications\Notification`)

```php
use Filament\Notifications\Notification;

Notification::make()
    ->title(__('booking.confirmed'))
    ->body(__('booking.confirmation_email_sent'))
    ->success()                 // ->warning(), ->danger(), ->info()
    ->icon('heroicon-o-check-badge')
    ->duration(5000)
    ->actions([
        \Filament\Notifications\Actions\Action::make('view')
            ->button()
            ->url(...),
    ])
    ->send();
```

**Use these for in-app feedback after admin actions (approve, reject, refund).**
**Do NOT confuse with the domain Notification system that dispatches push/email/SMS — those go through `DispatchNotificationAction`.**

---

## 5. Widgets (`Filament\Widgets`)

Dashboard widgets for the admin overview page.

| Widget | Use when |
|---|---|
| `StatsOverviewWidget` | Headline metrics (total bookings, revenue, active vendors) |
| `ChartWidget` | Time-series charts (revenue per week, bookings per type) |
| `TableWidget` | Embedded table (e.g., latest 10 pending vendor approvals) |
| `Widget` (custom) | Anything else — render any Blade view |

### Stats widget convention

```php
class BookingStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(__('dashboard.bookings_today'), Booking::query()->whereDate('created_at', today())->count())
                ->description(__('dashboard.compared_yesterday'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make(__('dashboard.revenue_today'), Money::ofMinor(
                Booking::query()->whereDate('created_at', today())->sum('total_minor'),
                'EGP'
            )->formatTo(app()->getLocale())),

            Stat::make(__('dashboard.pending_vendors'), VendorProfile::query()->where('approval_status', 'pending')->count())
                ->color('warning'),
        ];
    }
}
```

### Chart widget convention (per product type)

```php
class BookingsByTypeChart extends ChartWidget
{
    protected static ?string $heading = 'Bookings by product type';

    protected function getData(): array
    {
        return [
            'datasets' => [
                ['label' => 'Rental',  'data' => $this->bookingsCount(ProductType::Rental)],
                ['label' => 'Sale',    'data' => $this->bookingsCount(ProductType::Sale)],
                ['label' => 'Digital', 'data' => $this->bookingsCount(ProductType::Digital)],
            ],
            'labels' => $this->last7Days(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

---

## 6. Plugin-specific patterns (curated plugins from `10_Package_List.md`)

### `filament/spatie-laravel-translatable-plugin`

```php
use Filament\Resources\Concerns\Translatable;

class RentalServiceResource extends Resource
{
    use Translatable;
}
```

Then in the form:

```php
TextInput::make('name')
    ->required()
    ->maxLength(255),
```

The plugin auto-handles per-locale storage. The Resource shows a top-bar locale switcher (EN / العربية).

### `filament/spatie-laravel-media-library-plugin`

```php
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;

SpatieMediaLibraryFileUpload::make('gallery')
    ->collection('gallery')
    ->multiple()
    ->reorderable()
    ->image()
    ->maxFiles(11)
    ->responsiveImages(),

// Table column:
SpatieMediaLibraryImageColumn::make('gallery')
    ->collection('gallery')
    ->circular()
    ->stacked()
    ->limit(3),
```

### `bezhansalleh/filament-shield`

After every new Resource:

```bash
php artisan shield:generate --all
```

Then update `config/filament-shield.php` to match per-product-type permission names if needed.

### `awcodes/filament-tiptap-editor`

Use ONLY for CMS pages and notification template HTML body (Phase 1.5). Don't use for service descriptions — service descriptions use `Textarea` to keep imports simple.

```php
use FilamentTiptapEditor\TiptapEditor;

TiptapEditor::make('body')
    ->profile('default')
    ->required(),
```

### `bezhansalleh/filament-language-switch`

Configure once in `app/Providers/Filament/AdminPanelProvider.php`:

```php
->plugin(\BezhanSalleh\LanguageSwitch\LanguageSwitchPlugin::make()
    ->locales(['en', 'ar'])
    ->visible(outsidePanels: false))
```

### `pxlrbt/filament-excel`

```php
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

->bulkActions([
    ExportBulkAction::make()->exports([
        ExcelExport::make()->fromTable()->withColumns([
            // Override columns here
        ]),
    ]),
])
```

### `saade/filament-fullcalendar`

For booking timeline view. Use it on the Booking Item Fulfillment Monitor page. Each event is a `BookingItem` with its scheduled slot.

---

## 7. Inviolable Filament rules for InstaParty

1. **Per-product-type Resources only.** `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` — never one Resource that handles all three types.
2. **Group `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` under "Services" navigation** with `protected static ?string $navigationGroup = 'Services';`.
3. **Translatable fields use the translatable plugin's locale tabs** at the top of the form.
4. **Money columns use `->money('EGP', divideBy: 100)`** — never display `_minor` raw.
5. **`product_type` columns use `->badge()` with the per-type color map** above.
6. **Run `shield:generate --all` after every new Resource.**
7. **Use `Notification::make()` for in-app feedback** — do not bypass and call `session()->flash()`.
8. **Custom Actions delegate to Application Actions** (e.g., `ApproveVendorForTypeAction::execute()`) — never put business logic in the Filament `->action()` closure.
9. **Filter every monitor page by `product_type`** when the underlying entity has the column.
10. **Use Filament's locale switcher** to test every Resource in EN AND AR before considering it done.

---

## 8. File location convention

All Filament resources for InstaParty live under their owning module:

```
app/Modules/Catalog/Filament/Resources/RentalServiceResource.php
app/Modules/Catalog/Filament/Resources/SaleServiceResource.php
app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php
app/Modules/Identity/Filament/Resources/VendorProfileResource.php
app/Modules/Booking/Filament/Resources/BookingResource.php
...
```

The custom Filament resource discovery in `App\Providers\Filament\AdminPanelProvider` scans `app/Modules/*/Filament/Resources/` recursively. **Never put module Resources directly in `app/Filament/`.**
