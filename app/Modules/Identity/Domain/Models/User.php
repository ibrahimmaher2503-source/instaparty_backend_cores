<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $email
 * @property string $phone_e164
 * @property Carbon|null $phone_verified_at
 * @property string $preferred_locale
 * @property string $timezone
 * @property string $numeral_system
 * @property string $status
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CustomerProfile|null $customerProfile
 * @property-read VendorProfile|null $vendorProfile
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicId, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'public_id',
        'name',
        'email',
        'password',
        'phone_e164',
        'phone_verified_at',
        'preferred_locale',
        'timezone',
        'numeral_system',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function vendorProfile(): HasOne
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function customerAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function scopeVendors(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'vendor'));
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'customer'));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'vendor' => $this->hasRole('vendor') && $this->status !== 'suspended',
            default  => $this->hasAnyRole(['admin', 'booking_manager', 'super_admin', 'panel_user']),
        };
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
