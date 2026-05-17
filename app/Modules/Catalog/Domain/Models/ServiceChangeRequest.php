<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceChangeRequestFactory;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ServiceChangeRequest extends Model
{
    use HasFactory;
    use HasPublicId;
    use HasTranslations;

    protected static function newFactory(): ServiceChangeRequestFactory
    {
        return ServiceChangeRequestFactory::new();
    }

    protected $fillable = [
        'public_id',
        'service_id',
        'product_type',
        'vendor_profile_id',
        'submitted_by',
        'status',
        'proposed_changes',
        'before_snapshot',
        'vendor_note',
        'admin_note',
        'clarification_round',
        'decided_by',
        'decided_at',
        'version',
    ];

    /** @var list<string> */
    public array $translatable = ['vendor_note', 'admin_note'];

    protected $casts = [
        'product_type' => ProductType::class,
        'status' => ServiceChangeRequestStatus::class,
        'proposed_changes' => 'array',
        'before_snapshot' => 'array',
        'clarification_round' => 'integer',
        'version' => 'integer',
        'decided_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceChangeRequestItem::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ServiceChangeRequestMessage::class)->orderBy('created_at');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ServiceChangeRequestStatus::Pending->value,
            ServiceChangeRequestStatus::AwaitingClarification->value,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Performs an optimistic-lock version-increment UPDATE.
     * Returns false if the version was already bumped by another process.
     */
    public function incrementVersion(): bool
    {
        $affected = static::query()
            ->where('id', $this->id)
            ->where('version', $this->version)
            ->update(['version' => $this->version + 1]);

        if ($affected === 1) {
            $this->version = $this->version + 1;

            return true;
        }

        return false;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
