<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Shared\Domain\Enums\ChangeRequestItemStatus;
use App\Modules\Shared\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequestItem extends Model
{
    use HasPublicId;

    protected $table = 'change_request_items';

    public $timestamps = false;

    protected $casts = [
        'item_status' => ChangeRequestItemStatus::class,
        'current_value_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    protected $fillable = [
        'public_id',
        'change_request_id',
        'field_path',
        'current_value_snapshot',
        'requested_change_en',
        'requested_change_ar',
        'item_status',
    ];

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class, 'change_request_id');
    }
}
