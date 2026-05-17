<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\Policies\ServiceEditApprovalPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RequestServiceChangeClarificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cr = $this->route('serviceChangeRequest');
        if (!$cr instanceof ServiceChangeRequest) {
            return false;
        }

        if ($cr->clarification_round >= ServiceEditApprovalPolicy::MAX_CLARIFICATIONS) {
            return false;
        }

        return Gate::allows('service.moderate.'.$cr->product_type->value, $cr->service);
    }

    public function rules(): array
    {
        return [
            'admin_note'    => ['required', 'array'],
            'admin_note.en' => ['required', 'string', 'max:4000'],
            'admin_note.ar' => ['required', 'string', 'max:4000'],
            'version'       => ['required', 'integer', 'min:1'],
        ];
    }
}
