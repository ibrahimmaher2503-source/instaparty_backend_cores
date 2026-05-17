<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Application\Actions\GetNavigationMenuAction;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationMenuController
{
    /**
     * @group Theme
     *
     * Get a navigation menu by slot.
     *
     * @queryParam slot string required One of: header, footer_primary, footer_secondary, mobile_drawer.
     */
    public function show(Request $request, GetNavigationMenuAction $action): JsonResponse
    {
        $slot = NavigationSlot::tryFrom((string) $request->query('slot', ''));

        if ($slot === null) {
            return ApiResponse::error('Unknown slot.', 422);
        }

        $payload = $action->execute($slot, app()->getLocale());

        return ApiResponse::success($payload)
            ->header('Cache-Control', 'public, max-age=300, must-revalidate');
    }
}
