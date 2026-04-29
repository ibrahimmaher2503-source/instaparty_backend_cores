<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Domain\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadVendorDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('vendor') ?? false;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['required', 'string', Rule::in(array_column(DocumentType::cases(), 'value'))],
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB
                'mimetypes:application/pdf,image/jpeg,image/png',
                'mimes:pdf,jpg,jpeg,png',
            ],
        ];
    }
}
