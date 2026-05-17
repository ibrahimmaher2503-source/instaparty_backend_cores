<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests\Admin;

use App\Modules\Communication\Application\DTOs\ResolveChatFlagDTO;
use App\Modules\Communication\Domain\Enums\ChatFlagResolution;
use Illuminate\Foundation\Http\FormRequest;

class ResolveChatFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @bodyParam decision string required Resolution decision. One of: upheld_redact, upheld_warn, upheld_block, dismissed_false_positive. Example: upheld_redact
     * @bodyParam note_en string required English-language reviewer note. Example: "Confirmed phone number — message redacted."
     * @bodyParam note_ar string required Arabic-language reviewer note. Example: "تم تأكيد وجود رقم هاتف — تم إخفاء الرسالة."
     */
    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                'string',
                'in:upheld_redact,upheld_warn,upheld_block,dismissed_false_positive',
            ],
            'note_en'  => ['required', 'string', 'min:5', 'max:500'],
            'note_ar'  => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function toDTO(): ResolveChatFlagDTO
    {
        return new ResolveChatFlagDTO(
            decision: ChatFlagResolution::from((string) $this->input('decision')),
            noteEn: (string) $this->input('note_en'),
            noteAr: (string) $this->input('note_ar'),
        );
    }
}
