<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceChangeRequestItemFactory;
use App\Modules\Catalog\Domain\Enums\ServiceFieldClassification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class ServiceChangeRequestItem extends Model
{
    use HasFactory;

    // No updated_at — append-only table
    public const UPDATED_AT = null;

    protected static function newFactory(): ServiceChangeRequestItemFactory
    {
        return ServiceChangeRequestItemFactory::new();
    }

    protected $fillable = [
        'service_change_request_id',
        'field_path',
        'field_classification',
        'before_value',
        'after_value',
    ];

    protected $casts = [
        'field_classification' => ServiceFieldClassification::class,
        'before_value' => 'array',
        'after_value' => 'array',
    ];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('ServiceChangeRequestItem is append-only and cannot be updated.');
        }

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('ServiceChangeRequestItem is append-only and cannot be updated.');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceChangeRequest::class);
    }

    /**
     * Reach the Service through the owning ServiceChangeRequest.
     *
     * HasOneThrough: ServiceChangeRequestItem → ServiceChangeRequest → Service
     *   $related   = Service::class
     *   $through   = ServiceChangeRequest::class
     *   $firstKey  = 'id'                          (PK of ServiceChangeRequest referenced by items.service_change_request_id)
     *   $secondKey = 'id'                          (PK of Service referenced by change_requests.service_id)
     *   $localKey  = 'service_change_request_id'   (FK on this table)
     *   $secondLocalKey = 'service_id'             (FK on service_change_requests)
     */
    public function service(): HasOneThrough
    {
        return $this->hasOneThrough(
            Service::class,
            ServiceChangeRequest::class,
            'id',          // FK on service_change_requests that matches $this->service_change_request_id
            'id',          // FK on services that matches service_change_requests.service_id
            'service_change_request_id', // local key on this table
            'service_id',  // local key on service_change_requests
        );
    }
}
