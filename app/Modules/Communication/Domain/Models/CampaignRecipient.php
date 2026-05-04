<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Database\Factories\CampaignRecipientFactory;
use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    use HasFactory;

    protected $table = 'campaign_recipients';

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory(): CampaignRecipientFactory
    {
        return CampaignRecipientFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => CampaignRecipientStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CampaignRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CampaignRun::class, 'campaign_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<NotificationDispatch, $this> */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(NotificationDispatch::class, 'dispatch_id');
    }
}
