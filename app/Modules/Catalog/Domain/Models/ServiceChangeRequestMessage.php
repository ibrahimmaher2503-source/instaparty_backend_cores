<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceChangeRequestMessageFactory;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ServiceChangeRequestMessage extends Model
{
    use HasFactory;
    use HasTranslations;

    // No updated_at — append-only table
    public const UPDATED_AT = null;

    protected static function newFactory(): ServiceChangeRequestMessageFactory
    {
        return ServiceChangeRequestMessageFactory::new();
    }

    /** @var list<string> */
    public array $translatable = ['body'];

    protected $fillable = [
        'service_change_request_id',
        'author_user_id',
        'author_role',
        'body',
        'clarification_round',
    ];

    protected $casts = [
        'clarification_round' => 'integer',
    ];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('ServiceChangeRequestMessage is append-only and cannot be updated.');
        }

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('ServiceChangeRequestMessage is append-only and cannot be updated.');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceChangeRequest::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
