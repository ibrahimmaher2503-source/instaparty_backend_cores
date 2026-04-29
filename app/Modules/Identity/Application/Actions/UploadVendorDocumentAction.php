<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadVendorDocumentAction
{
    public function execute(VendorProfile $vendorProfile, UploadedFile $file, DocumentType $docType): VendorDocument
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $storedName = (string) Str::ulid().'.'.$extension;
        $directory = "vendors/{$vendorProfile->id}/documents";
        $path = $file->storeAs($directory, $storedName, 's3-private');

        if ($path === false) {
            throw new \RuntimeException('Failed to store vendor document.');
        }

        try {
            return DB::transaction(fn (): VendorDocument => VendorDocument::create([
                'vendor_profile_id' => $vendorProfile->id,
                'doc_type' => $docType->value,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'status' => DocumentStatus::Pending->value,
            ]));
        } catch (Throwable $e) {
            Storage::disk('s3-private')->delete($path);
            throw $e;
        }
    }
}
