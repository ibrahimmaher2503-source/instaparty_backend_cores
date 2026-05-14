<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Shared\Domain\Models\ChangeRequestItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorResubmitFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('vendor');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'addressed_item_ids' => ['required', 'array'],
            'addressed_item_ids.*' => [
                'string',
                Rule::exists(ChangeRequestItem::class, 'public_id'),
            ],
            'waived_item_ids' => ['sometimes', 'array'],
            'waived_item_ids.*' => [
                'string',
                Rule::exists(ChangeRequestItem::class, 'public_id'),
            ],
            'resubmit_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
