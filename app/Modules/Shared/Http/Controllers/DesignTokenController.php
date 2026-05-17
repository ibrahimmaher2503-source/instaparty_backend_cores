<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Application\Actions\GetActiveDesignTokensAction;
use App\Modules\Shared\Http\ApiResponse;
use App\Modules\Shared\Http\Resources\DesignTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignTokenController
{
    /**
     * @group Theme
     *
     * Get the currently active design tokens.
     *
     * Returns the design-token JSON the customer frontend should render with.
     * Falls back to a built-in default if no token row is active.
     *
     * @response 200 {"data":{"public_id":"01H...","name":"default","tokens":{...},"updated_at":"2026-05-17T12:00:00.000000Z"},"meta":{},"errors":null}
     */
    public function show(Request $request, GetActiveDesignTokensAction $action): JsonResponse
    {
        $payload = $action->execute();
        $etag = '"'.hash('sha256', (string) json_encode($payload['tokens'])).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->json(null, 304)->header('ETag', $etag);
        }

        return ApiResponse::success(new DesignTokenResource($payload))
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=300, must-revalidate');
    }
}
