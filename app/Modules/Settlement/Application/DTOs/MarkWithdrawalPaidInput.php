<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

use Illuminate\Http\UploadedFile;

final readonly class MarkWithdrawalPaidInput
{
    private const ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    /**
     * @param array{en?: string, ar?: string} $paymentNote
     */
    public function __construct(
        public string $bankTransferReference,
        public UploadedFile $proofFile,
        public array $paymentNote = [],
    ) {}

    public function assertValid(): void
    {
        if (trim($this->bankTransferReference) === '') {
            throw new \InvalidArgumentException(
                __('settlement.validation.bank_transfer_reference_required')
            );
        }

        if (! $this->proofFile->isValid()) {
            throw new \InvalidArgumentException(
                __('settlement.validation.proof_required')
            );
        }

        $mime = $this->proofFile->getMimeType() ?? '';
        $size = $this->proofFile->getSize() ?: 0;

        if (! in_array($mime, self::ALLOWED_MIMES, true) || $size > self::MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException(
                __('settlement.validation.proof_format')
            );
        }
    }
}
