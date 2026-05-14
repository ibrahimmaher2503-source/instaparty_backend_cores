<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\Loyalty\Database\Factories\LoyaltyRuleFactory;
use App\Modules\Loyalty\Domain\Enums\RuleKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class LoyaltyRule extends Model
{
    use HasFactory, HasTranslations, HasUlids;

    protected $table = 'loyalty_rules';

    public array $translatable = ['label'];

    protected $fillable = [
        'public_id',
        'loyalty_program_id',
        'rule_kind',
        'multiplier',
        'conditions',
        'label',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'rule_kind' => RuleKind::class,
            'multiplier' => 'decimal:2',
            'conditions' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function newFactory(): LoyaltyRuleFactory
    {
        return LoyaltyRuleFactory::new();
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }
}
