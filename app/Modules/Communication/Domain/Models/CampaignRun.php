<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $recipients_total
 * @property int $recipients_sent
 * @property int $recipients_failed
 */
class CampaignRun extends Model
{
    protected $table = 'campaign_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return HasMany<CampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function hasQueuedRecipients(): bool
    {
        return $this->recipients()->where('status', 'queued')->exists();
    }
}
