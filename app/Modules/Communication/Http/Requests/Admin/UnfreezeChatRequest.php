<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests\Admin;

use App\Modules\Communication\Application\DTOs\UnfreezeChatDTO;
use Illuminate\Foundation\Http\FormRequest;

class UnfreezeChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @bodyParam reason_en string required English-language reason for unfreezing the thread. Example: "Investigation cleared — content was vendor's own venue address, allowed."
     * @bodyParam reason_ar string required Arabic-language reason for unfreezing the thread. Example: "تبين بعد التحقيق أن المحتوى عنوان قاعة المورد، وهو مسموح."
     */
    public function rules(): array
    {
        return [
            'reason_en' => ['required', 'string', 'min:5', 'max:500'],
            'reason_ar' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function toDTO(): UnfreezeChatDTO
    {
        return new UnfreezeChatDTO(
            reasonEn: (string) $this->input('reason_en'),
            reasonAr: (string) $this->input('reason_ar'),
        );
    }
}
