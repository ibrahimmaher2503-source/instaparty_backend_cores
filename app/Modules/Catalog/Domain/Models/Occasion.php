<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\OccasionFactory;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Occasion extends Model
{
    use HasFactory;
    use HasPublicId;
    use HasTranslations;
    use SoftDeletes;

    protected static function newFactory(): OccasionFactory
    {
        return OccasionFactory::new();
    }

    protected $fillable = [
        'public_id',
        'code',
        'name',
        'description',
        'icon_path',
        'sort_order',
        'is_active',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'occasion_category')
            ->withPivot('sort_order');
    }
}
