<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Database\Factories\UserDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    /** @use HasFactory<UserDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'fcm_token',
        'device_id',
        'last_seen_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): UserDeviceFactory
    {
        return UserDeviceFactory::new();
    }

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
