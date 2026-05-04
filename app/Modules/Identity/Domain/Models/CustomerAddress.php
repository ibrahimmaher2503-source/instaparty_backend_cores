<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $city_id
 * @property string $label
 * @property string $address_line
 * @property string|null $building
 * @property string|null $floor
 * @property string|null $apartment
 * @property string|null $landmark
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $recipient_name
 * @property string $recipient_phone_e164
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'public_id',
        'user_id',
        'city_id',
        'label',
        'address_line',
        'building',
        'floor',
        'apartment',
        'landmark',
        'latitude',
        'longitude',
        'recipient_name',
        'recipient_phone_e164',
        'is_default',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function newFactory(): CustomerAddressFactory
    {
        return CustomerAddressFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
