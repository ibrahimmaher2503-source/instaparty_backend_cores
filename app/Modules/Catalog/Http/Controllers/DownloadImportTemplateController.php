<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\DownloadTemplateAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Vendor - Excel Import
 */
class DownloadImportTemplateController
{
    public function __invoke(Request $request, string $type, DownloadTemplateAction $action): BinaryFileResponse
    {
        abort_unless(collect(ProductType::cases())->map->value->contains($type), 422, 'Invalid product type.');

        return $action->execute(ProductType::from($type));
    }
}
