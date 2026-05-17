<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests\Admin;

use App\Modules\Communication\Application\DTOs\EscalateChatFlagDTO;
use Illuminate\Foundation\Http\FormRequest;

class EscalateChatFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @bodyParam severity string required Inbox severity. One of: info, warning, critical. Example: warning
     * @bodyParam summary_en string required English-language summary. Example: "Vendor invited customer off-platform; thread already frozen."
     * @bodyParam summary_ar string required Arabic-language summary. Example: "دعا المورد العميل خارج المنصة، وتم تجميد المحادثة بالفعل."
     */
    public function rules(): array
    {
        return [
            'severity'   => ['required', 'string', 'in:info,warning,critical'],
            'summary_en' => ['required', 'string', 'min:5', 'max:280'],
            'summary_ar' => ['required', 'string', 'min:5', 'max:280'],
        ];
    }

    public function toDTO(int $flagId): EscalateChatFlagDTO
    {
        return new EscalateChatFlagDTO(
            chatModerationFlagId: $flagId,
            severity: (string) $this->input('severity'),
            summaryEn: (string) $this->input('summary_en'),
            summaryAr: (string) $this->input('summary_ar'),
        );
    }
}
