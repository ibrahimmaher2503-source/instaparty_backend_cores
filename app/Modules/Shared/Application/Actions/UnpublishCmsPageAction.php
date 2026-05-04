<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Models\CmsPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UnpublishCmsPageAction
{
    public function execute(CmsPage $page): CmsPage
    {
        return DB::transaction(function () use ($page): CmsPage {
            $page->is_published = false;
            $page->updated_by = Auth::id();
            $page->save();

            return $page;
        });
    }
}
