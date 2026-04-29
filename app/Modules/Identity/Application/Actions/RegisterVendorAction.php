<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTOs\RegisterVendorDTO;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterVendorAction
{
    public function __construct(private readonly SendOtpAction $sendOtp) {}

    public function execute(RegisterVendorDTO $dto): VendorProfile
    {
        $vendorProfile = DB::transaction(function () use ($dto): VendorProfile {
            $user = User::create([
                'name' => $dto->name,
                'phone_e164' => $dto->phoneE164,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'preferred_locale' => $dto->preferredLocale,
                'status' => 'active',
                'timezone' => 'Africa/Cairo',
                'numeral_system' => 'western',
            ]);

            $user->assignRole('vendor');

            $vendorProfile = VendorProfile::create([
                'user_id' => $user->id,
                'business_name' => $dto->businessName,
                'slug' => $this->generateUniqueSlug($dto->businessName['en']),
                'business_type' => $dto->businessType,
                'primary_governorate_id' => $dto->primaryGovernorateId,
                'primary_city_id' => $dto->primaryCityId,
                'approval_status' => ApprovalStatus::Pending->value,
            ]);

            DB::afterCommit(fn () => event(new VendorRegistered($vendorProfile)));

            return $vendorProfile;
        });

        $user = User::findOrFail($vendorProfile->user_id);
        $this->sendOtp->execute($user->phone_e164);
        $vendorProfile->load(['user', 'documents', 'approvedTypes']);

        return $vendorProfile;
    }

    private function generateUniqueSlug(string $businessNameEn): string
    {
        $base = Str::slug($businessNameEn) ?: 'vendor';
        $slug = $base;
        $i = 0;

        while (VendorProfile::query()->where('slug', $slug)->exists()) {
            $i++;
            $slug = "{$base}-".Str::lower(Str::random(4));
            if ($i > 5) {
                $slug = $base.'-'.Str::ulid();
                break;
            }
        }

        return $slug;
    }
}
