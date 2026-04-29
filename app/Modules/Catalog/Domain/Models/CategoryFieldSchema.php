<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\CategoryFieldSchemaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class CategoryFieldSchema extends Model
{
    use HasFactory;
    use HasTranslations;

    protected static function newFactory(): CategoryFieldSchemaFactory
    {
        return CategoryFieldSchemaFactory::new();
    }

    protected $fillable = [
        'category_id',
        'product_type',
        'field_key',
        'field_label',
        'field_type',
        'options',
        'is_required',
        'is_filterable',
        'validation_rules',
        'sort_order',
    ];

    /** @var list<string> */
    public array $translatable = ['field_label'];

    protected $casts = [
        'options'          => 'array',
        'validation_rules' => 'array',
        'is_required'      => 'boolean',
        'is_filterable'    => 'boolean',
        'sort_order'       => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
