<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceRentalDetailFactory;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRentalDetail extends Model
{
    use HasFactory;

    protected static function newFactory(): ServiceRentalDetailFactory
    {
        return ServiceRentalDetailFactory::new();
    }

    protected $primaryKey = 'service_id';

    public $incrementing = false;

    protected $fillable = [
        'service_id',
        'requires_electricity',
        'requires_outdoor_space',
        'default_rental_duration_hours',
        'setup_time_minutes',
        'teardown_time_minutes',
        'security_deposit_minor',
        'security_deposit_currency',
        'minimum_space_sqm',
    ];

    protected $casts = [
        'requires_electricity' => 'boolean',
        'requires_outdoor_space' => 'boolean',
        'default_rental_duration_hours' => 'integer',
        'setup_time_minutes' => 'integer',
        'teardown_time_minutes' => 'integer',
        'security_deposit' => MoneyCast::class.':security_deposit',
        'minimum_space_sqm' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
