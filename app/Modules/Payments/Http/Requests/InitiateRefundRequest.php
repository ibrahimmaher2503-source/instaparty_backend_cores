<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @bodyParam reason_code string required Refund reason. Example: customer_request
     * @bodyParam reason_notes object required Translatable reason notes. Example: {"en":"Customer request","ar":"طلب العميل"}
     * @bodyParam amount_minor integer Optional explicit refund amount in piastres; must equal the captured payment amount (Phase 1 full-only). Example: 50000
     */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', Rule::in(RefundReasonCode::values())],
            'reason_notes' => ['required', 'array'],
            'reason_notes.en' => ['required', 'string', 'min:1'],
            'reason_notes.ar' => ['required', 'string', 'min:1'],
            'amount_minor' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
