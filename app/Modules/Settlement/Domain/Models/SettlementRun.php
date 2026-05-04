<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Settlement\Database\Factories\SettlementRunFactory;
use App\Modules\Settlement\Domain\Enums\SettlementRunStatus;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettlementRun extends Model
{
    /** @use HasFactory<SettlementRunFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'public_id',
        'period_start',
        'period_end',
        'total_gross_minor',
        'total_gross_currency',
        'total_commission_minor',
        'total_commission_currency',
        'total_vendor_share_minor',
        'total_vendor_share_currency',
        'status',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'status' => SettlementRunStatus::class,
        'total_gross_minor' => 'integer',
        'total_commission_minor' => 'integer',
        'total_vendor_share_minor' => 'integer',
    ];

    protected static function newFactory(): SettlementRunFactory
    {
        return SettlementRunFactory::new();
    }
}
