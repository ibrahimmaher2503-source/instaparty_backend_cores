<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ExcelImportErrorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcelImportError extends Model
{
    /** @use HasFactory<ExcelImportErrorFactory> */
    use HasFactory;

    // Append-only — only created_at, no updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'excel_import_id',
        'row_number',
        'field',
        'message',
    ];

    protected $casts = [
        'message' => 'array',
        'row_number' => 'integer',
    ];

    protected static function newFactory(): ExcelImportErrorFactory
    {
        return ExcelImportErrorFactory::new();
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function import(): BelongsTo
    {
        return $this->belongsTo(ExcelImport::class, 'excel_import_id');
    }
}
