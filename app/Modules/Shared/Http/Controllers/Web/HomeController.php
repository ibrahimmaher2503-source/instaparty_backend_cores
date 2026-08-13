<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers\Web;

use App\Modules\Geography\Domain\Contracts\GeographyRepository;
use App\Modules\Shared\Application\Actions\GetHomepageDataAction;
use Illuminate\Contracts\View\View;

/**
 * Storefront home page.
 *
 * Placeholder shell for Phase 0 — it exists so the /{locale} routing chain is
 * exercised end to end. Phase 1 replaces the body with the CMS-driven block
 * renderer backed by the existing /cms/homepage endpoint's action.
 */
class HomeController
{
    public function __invoke(
        GetHomepageDataAction $action,
        GeographyRepository $geography,
    ): View {
        return view('storefront.home', [
            'homepage' => $action->execute(app()->getLocale()),
            'cities' => $geography->searchCities(''),
        ]);
    }
}
