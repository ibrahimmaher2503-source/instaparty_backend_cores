<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Database\Factories\WithdrawalFactory;
use App\Modules\Settlement\Domain\Casts\BankAccountSnapshotCast;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\WithdrawalState;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\ModelStates\HasStates;

/**
 * @property string $public_id
 * @property int $vendor_profile_id
 * @property int $requested_amount_minor
 * @property string $requested_amount_currency
 * @property int|null $paid_amount_minor
 * @property string|null $paid_amount_currency
 * @property BankAccountSnapshot|null $bank_account_snapshot
 * @property WithdrawalState $status
 * @property array{en: string, ar: string}|null $rejected_reason
 * @property int $requested_by_user_id
 * @property int|null $processed_by_user_id
 * @property int|null $approved_by_admin_id
 * @property int|null $paid_by_admin_id
 * @property int|null $bank_proof_media_id
 * @property string|null $bank_transfer_reference
 * @property array{en?: string, ar?: string}|null $admin_payment_note
 * @property Carbon|null $requested_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property int|null $pending_lock
 */
class Withdrawal extends Model implements HasMedia
{
    /** @use HasFactory<WithdrawalFactory> */
    use HasFactory;

    use HasPublicId;
    use HasStates;
    use InteractsWithMedia;

    /** @var array<int, string> */
    public array $translatable = ['rejected_reason', 'admin_payment_note'];

    protected $fillable = [
        'public_id',
        'status',
        'vendor_profile_id',
        'requested_amount_minor',
        'requested_amount_currency',
        'paid_amount_minor',
        'paid_amount_currency',
        'bank_account_snapshot',
        'rejected_reason',
        'requested_by_user_id',
        'processed_by_user_id',
        'bank_proof_media_id',
        'requested_at',
        'processed_at',
        'paid_at',
        'pending_lock',
        // Phase 4.9 — ledger links
        'idempotency_key',
        'reserved_ledger_entry_id',
        'settled_ledger_entry_id',
        'rejected_ledger_entry_id',
        // Phase 4.11 — finance audit columns
        'approved_at',
        'approved_by_admin_id',
        'paid_by_admin_id',
        'bank_transfer_reference',
        'admin_payment_note',
    ];

    protected $casts = [
        'status' => WithdrawalState::class,
        'bank_account_snapshot' => BankAccountSnapshotCast::class,
        'rejected_reason' => 'array',
        'admin_payment_note' => 'array',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'requested_amount_minor' => 'integer',
        'paid_amount_minor' => 'integer',
        'vendor_profile_id' => 'integer',
        'requested_by_user_id' => 'integer',
        'processed_by_user_id' => 'integer',
        'approved_by_admin_id' => 'integer',
        'paid_by_admin_id' => 'integer',
        'pending_lock' => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('bank_proof')
            ->singleFile()
            ->useDisk('s3-private')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }

    /** @return BelongsTo<VendorProfile, $this> */
    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function reservedLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerEntry::class, 'reserved_ledger_entry_id');
    }

    public function settledLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerEntry::class, 'settled_ledger_entry_id');
    }

    public function rejectedLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerEntry::class, 'rejected_ledger_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    /** @return BelongsTo<User, $this> */
    public function paidByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_admin_id');
    }

    protected static function newFactory(): WithdrawalFactory
    {
        return WithdrawalFactory::new();
    }
}
