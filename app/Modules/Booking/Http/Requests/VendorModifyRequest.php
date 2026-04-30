<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorModifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $proposalKinds = array_column(ModificationProposalKind::cases(), 'value');

        return [
            'proposal_kind'             => ['required', 'string', Rule::in($proposalKinds)],
            'vendor_explanation'        => ['nullable', 'array'],
            'vendor_explanation.en'     => ['nullable', 'string', 'max:500'],
            'vendor_explanation.ar'     => ['nullable', 'string', 'max:500'],
            'changes'                   => ['required', 'array', 'min:1'],
            'changes.*.change_kind'     => ['required', 'string', Rule::in(['add', 'remove', 'update'])],
            'changes.*.target_item_public_id' => ['nullable', 'string'],
            'changes.*.payload'         => ['nullable', 'array'],
        ];
    }
}
