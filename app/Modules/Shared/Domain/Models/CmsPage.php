<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Shared\Domain\Enums\CmsSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class CmsPage extends Model
{
    use HasPublicId;
    use HasTranslations;

    protected $table = 'cms_pages';

    public array $translatable = ['title', 'body', 'meta_description'];

    protected $fillable = [
        'public_id',
        'slug',
        'title',
        'body',
        'meta_description',
        'is_published',
        'published_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'slug' => CmsSlug::class,
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
