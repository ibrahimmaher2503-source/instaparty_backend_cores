<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property CampaignChannel $channel
 * @property CampaignTargetLocale $target_locale
 * @property CampaignStatus $status
 * @property array<string, mixed> $segment_filters
 */
class Campaign extends Model
{
    use HasTranslations;

    protected $table = 'campaigns';

    protected $guarded = [];

    /** @var list<string> */
    public array $translatable = ['subject', 'body'];

    protected function casts(): array
    {
        return [
            'channel' => CampaignChannel::class,
            'target_locale' => CampaignTargetLocale::class,
            'status' => CampaignStatus::class,
            'segment_filters' => 'array',
            'product_type_segment' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }

    /** @return HasMany<CampaignRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(CampaignRun::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Draft->value);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Running->value);
    }

    public function isDraft(): bool
    {
        return $this->status === CampaignStatus::Draft;
    }

    public function isRunning(): bool
    {
        return $this->status === CampaignStatus::Running;
    }
}
