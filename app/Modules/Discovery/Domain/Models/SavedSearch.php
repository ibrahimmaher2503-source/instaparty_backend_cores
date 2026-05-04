<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Domain\Models;

use App\Modules\Discovery\Database\Factories\SavedSearchFactory;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'filters',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    protected static function newFactory(): SavedSearchFactory
    {
        return SavedSearchFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
