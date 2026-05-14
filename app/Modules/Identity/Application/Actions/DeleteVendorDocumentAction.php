<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteVendorDocumentAction
{
    public function execute(VendorProfile $vendorProfile, VendorDocument $document): void
    {
        if ($document->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages([
                'document' => __('identity.errors.document_not_owned'),
            ]);
        }

        if ($document->status !== DocumentStatus::Rejected) {
            throw ValidationException::withMessages([
                'document' => __('identity.errors.document_not_deletable'),
            ]);
        }

        DB::transaction(function () use ($document): void {
            $path = $document->file_path;
            $document->delete();

            DB::afterCommit(fn () => Storage::disk('s3-private')->delete($path));
        });
    }
}
