<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Database\Factories\VendorDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $public_id
 * @property int $vendor_profile_id
 * @property DocumentType $doc_type
 * @property string $file_path
 * @property string $file_name
 * @property DocumentStatus $status
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property array<string, string>|null $review_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VendorDocument extends Model
{
    /** @use HasFactory<VendorDocumentFactory> */
    use HasFactory, HasPublicId, HasTranslations;

    /** @var array<int, string> */
    public $translatable = ['review_notes'];

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'doc_type',
        'file_path',
        'file_name',
        'status',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'expires_at',
        'is_critical',
        'last_reminder_sent_at',
    ];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    protected static function newFactory(): VendorDocumentFactory
    {
        return VendorDocumentFactory::new();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'doc_type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'reviewed_at' => 'datetime',
            'expires_at' => 'date',
            'is_critical' => 'bool',
            'last_reminder_sent_at' => 'date',
        ];
    }

    public function scopeWithExpiry($query)
    {
        return $query->whereNotNull('expires_at');
    }

    public function scopeExpiringWithinDays($query, int $days)
    {
        $expiryDate = today()->addDays($days)->toDateString();

        return $query->where('expires_at', '<=', $expiryDate);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now()->toDateString());
    }
}
