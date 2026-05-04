<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Domain\Enums\CmsSlug;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Shared\Http\ApiResponse;
use App\Modules\Shared\Http\Resources\CmsPageResource;
use Illuminate\Http\JsonResponse;

class CmsPageController
{
    /**
     * @group CMS
     *
     * Get a published CMS page by slug.
     *
     * Returns the page content in the locale specified by the Accept-Language header.
     * Only published pages are returned. Unpublished pages return 404.
     *
     * @urlParam slug string required The page slug. One of: terms, privacy, about, contact. Example: terms
     *
     * @response 200 {"data":{"slug":"terms","title":"Terms & Conditions","body":"<p>Terms...</p>","meta_description":"InstaParty Terms","published_at":"2026-05-03T10:00:00.000000Z"},"meta":{},"errors":null}
     * @response 404 {"data":null,"meta":{},"errors":{"message":"No query results for model"}}
     */
    public function show(CmsSlug $slug): JsonResponse
    {
        $page = CmsPage::published()->where('slug', $slug->value)->firstOrFail();

        return ApiResponse::success(new CmsPageResource($page));
    }
}
