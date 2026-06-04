<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\RegisterDeviceAction;
use App\Modules\Identity\Application\Actions\UnregisterDeviceAction;
use App\Modules\Identity\Http\Requests\RegisterDeviceRequest;
use App\Modules\Identity\Http\Resources\DeviceResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DeviceController extends Controller
{
    public function store(RegisterDeviceRequest $request, RegisterDeviceAction $action): JsonResponse
    {
        $device = $action->execute(
            userId: (int) $request->user()->id,
            platform: $request->validated('platform'),
            fcmToken: $request->validated('fcm_token'),
            deviceId: $request->validated('device_id'),
        );

        return ApiResponse::success(new DeviceResource($device), status: 201);
    }

    public function destroy(Request $request, string $fcmToken, UnregisterDeviceAction $action): JsonResponse
    {
        $action->execute((int) $request->user()->id, $fcmToken);

        return ApiResponse::success(null);
    }
}
