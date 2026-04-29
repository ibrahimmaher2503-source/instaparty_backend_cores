<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Database\Factories\CustomerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $date_of_birth
 * @property string|null $gender
 * @property string|null $how_heard_about_us
 * @property array<mixed>|null $children
 * @property bool $accepts_marketing
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomerProfile extends Model
{
    /** @use HasFactory<CustomerProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date_of_birth',
        'gender',
        'how_heard_about_us',
        'children',
        'accepts_marketing',
    ];

    protected static function newFactory(): CustomerProfileFactory
    {
        return CustomerProfileFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'children' => 'array',
            'accepts_marketing' => 'boolean',
        ];
    }
}
