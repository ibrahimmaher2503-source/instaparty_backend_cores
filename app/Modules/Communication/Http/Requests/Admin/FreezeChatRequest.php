<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests\Admin;

use App\Modules\Communication\Application\DTOs\FreezeChatDTO;
use App\Modules\Communication\Domain\Enums\ChatFreezeCategory;
use Illuminate\Foundation\Http\FormRequest;

class FreezeChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @bodyParam reason_en string required English-language reason for freezing the thread. Example: "Suspected phone number exchange to evade booking."
     * @bodyParam reason_ar string required Arabic-language reason for freezing the thread. Example: "اشتباه في تبادل أرقام هواتف للالتفاف على الحجز."
     * @bodyParam category string required Freeze category. One of: off_platform_contact, policy_violation, harassment, other. Example: off_platform_contact
     */
    public function rules(): array
    {
        return [
            'reason_en' => ['required', 'string', 'min:5', 'max:500'],
            'reason_ar' => ['required', 'string', 'min:5', 'max:500'],
            'category'  => [
                'required',
                'string',
                'in:off_platform_contact,policy_violation,harassment,other',
            ],
        ];
    }

    public function toDTO(): FreezeChatDTO
    {
        return new FreezeChatDTO(
            reasonEn: (string) $this->input('reason_en'),
            reasonAr: (string) $this->input('reason_ar'),
            category: ChatFreezeCategory::from((string) $this->input('category')),
        );
    }
}
