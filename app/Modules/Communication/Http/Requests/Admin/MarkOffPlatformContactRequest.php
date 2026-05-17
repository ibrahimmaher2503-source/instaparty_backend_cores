<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests\Admin;

use App\Modules\Communication\Application\DTOs\MarkOffPlatformContactDTO;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use Illuminate\Foundation\Http\FormRequest;

class MarkOffPlatformContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @bodyParam flag_type string required Detected violation category. One of: phone, email, external_link, other. Example: external_link
     * @bodyParam reason_en string required English-language reason for the manual mark. Example: "Vendor invited customer to direct-message on Instagram."
     * @bodyParam reason_ar string required Arabic-language reason for the manual mark. Example: "دعا المورد العميل إلى المراسلة المباشرة على إنستجرام."
     */
    public function rules(): array
    {
        return [
            // profanity is excluded — manual marks are for off-platform contact only.
            'flag_type' => ['required', 'string', 'in:phone,email,external_link,other'],
            'reason_en' => ['required', 'string', 'min:5', 'max:500'],
            'reason_ar' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function toDTO(int $logId): MarkOffPlatformContactDTO
    {
        return new MarkOffPlatformContactDTO(
            chatMessageLogId: $logId,
            flagType: ChatFlagType::from((string) $this->input('flag_type')),
            reasonEn: (string) $this->input('reason_en'),
            reasonAr: (string) $this->input('reason_ar'),
        );
    }
}
