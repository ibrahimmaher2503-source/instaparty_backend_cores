<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationFinding extends Model
{
    public $timestamps = false;

    protected $table = 'reconciliation_findings';

    protected $fillable = [
        'public_id',
        'reconciliation_run_id',
        'finding_type',
        'severity',
        'resource_type',
        'resource_id',
        'expected',
        'actual',
        'delta',
        'resolution',
        'resolved_by_user_id',
        'resolved_at',
        'description_key',
        'description_params',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'finding_type'       => ReconciliationFindingType::class,
            'severity'           => ReconciliationFindingSeverity::class,
            'expected'           => 'array',
            'actual'             => 'array',
            'delta'              => 'array',
            'description_params' => 'array',
            'resolved_at'        => 'datetime',
            'created_at'         => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'resolved_by_user_id');
    }
}
