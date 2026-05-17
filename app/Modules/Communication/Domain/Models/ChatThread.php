<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Database\Factories\ChatThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class ChatThread extends Model
{
    use HasFactory;

    protected static function newFactory(): ChatThreadFactory
    {
        return ChatThreadFactory::new();
    }

    protected $fillable = [
        'public_id',
        'firestore_thread_id',
        'customer_id',
        'vendor_profile_id',
        'booking_id',
        'status',
        'locked_at',
        'frozen_at',
        'frozen_by',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'frozen_at' => 'datetime',
    ];

    public function frozenByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Domain\Models\User::class, 'frozen_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Domain\Models\User::class, 'customer_id');
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Domain\Models\VendorProfile::class, 'vendor_profile_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Booking\Domain\Models\Booking::class, 'booking_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessageLog::class, 'chat_thread_id')->orderBy('created_at');
    }

    public function unresolvedFlags(): HasManyThrough
    {
        return $this->hasManyThrough(
            ChatModerationFlag::class,
            ChatMessageLog::class,
            'chat_thread_id',       // FK on ChatMessageLog
            'chat_message_log_id',  // FK on ChatModerationFlag
            'id',                   // local key on ChatThread
            'id',                   // local key on ChatMessageLog
        )->whereNull('reviewed_at');
    }

    public function bookingVendor(): HasOneThrough
    {
        return $this->hasOneThrough(
            \App\Modules\Booking\Domain\Models\BookingVendor::class,
            \App\Modules\Booking\Domain\Models\Booking::class,
            'id',                   // local key on Booking
            'booking_id',           // FK on BookingVendor
            'booking_id',           // local key on ChatThread
            'id',                   // local key on Booking
        )->where('vendor_profile_id', $this->vendor_profile_id);
    }

    public function scopeFrozen(Builder $query): Builder
    {
        return $query->whereNotNull('frozen_at');
    }

    public function scopeWithOpenFlags(Builder $query): Builder
    {
        return $query->whereHas('unresolvedFlags');
    }
}
