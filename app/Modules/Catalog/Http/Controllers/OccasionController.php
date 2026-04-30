<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OccasionController
{
    public function index(Request $request): JsonResponse
    {
        $occasions = Occasion::where('is_active', true)->orderBy('sort_order')->get();

        return ApiResponse::success($occasions);
    }
}
