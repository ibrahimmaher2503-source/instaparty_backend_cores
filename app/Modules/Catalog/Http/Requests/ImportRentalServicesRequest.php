<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam store_id string required Public ID (ULID) of the vendor's own vendor_profile. Example: "01HXYZ1234567890ABCDEFGHIJ"
 * @bodyParam file file required Excel file (.xlsx or .xls) containing rental services to import.
 */
class ImportRentalServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->user()?->vendorProfile;

        return $vendor?->approvedTypes->contains('product_type', ProductType::Rental) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'string', 'size:26'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    /**
     * Resolves the authenticated vendor's VendorProfile, aborting 403 if store_id doesn't match.
     */
    public function vendorProfile(): VendorProfile
    {
        $vendor = $this->user()->vendorProfile()->firstOrFail();

        if ($vendor->public_id !== $this->input('store_id')) {
            abort(403, 'store_not_owned');
        }

        return $vendor;
    }
}
