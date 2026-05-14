<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

readonly class SetDocumentExpiryAction
{
    public function execute(VendorDocument $document, Carbon $expiresAt, bool $isCritical): VendorDocument
    {
        if (! $expiresAt->isFuture()) {
            throw new InvalidArgumentException(__('identity.expiry_date_must_be_future'));
        }

        return DB::transaction(function () use ($document, $expiresAt, $isCritical) {
            $document->update([
                'expires_at' => $expiresAt->toDateString(),
                'is_critical' => $isCritical,
            ]);

            return $document->refresh();
        });
    }
}
