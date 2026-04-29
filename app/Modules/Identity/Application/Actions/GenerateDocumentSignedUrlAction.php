<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Support\Facades\Storage;

class GenerateDocumentSignedUrlAction
{
    private const int TTL_MINUTES = 15;

    public function execute(VendorDocument $document): string
    {
        return Storage::disk('s3-private')->temporaryUrl(
            $document->file_path,
            now()->addMinutes(self::TTL_MINUTES),
        );
    }
}
