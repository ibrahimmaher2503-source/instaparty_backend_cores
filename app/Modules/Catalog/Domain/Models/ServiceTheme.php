<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceThemeFactory;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ServiceTheme extends Model
{
    use HasFactory;
    use HasPublicId;
    use HasTranslations;

    protected static function newFactory(): ServiceThemeFactory
    {
        return ServiceThemeFactory::new();
    }

    protected $fillable = [
        'public_id',
        'code',
        'name',
        'icon_path',
        'is_active',
    ];

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
