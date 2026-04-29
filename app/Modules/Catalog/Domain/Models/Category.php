<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\CategoryFactory;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasFactory;
    use HasPublicId;
    use HasTranslations;
    use SoftDeletes;

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    protected $fillable = [
        'public_id',
        'parent_id',
        'code',
        'name',
        'description',
        'icon_path',
        'allowed_product_types',
        'sort_order',
        'is_active',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $casts = [
        'allowed_product_types' => 'array',
        'is_active'             => 'boolean',
        'sort_order'            => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function occasions(): BelongsToMany
    {
        return $this->belongsToMany(Occasion::class, 'occasion_category')
            ->withPivot('sort_order');
    }

    public function fieldSchemas(): HasMany
    {
        return $this->hasMany(CategoryFieldSchema::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProductType(Builder $query, string $type): Builder
    {
        return $query->whereJsonContains('allowed_product_types', $type);
    }
}
