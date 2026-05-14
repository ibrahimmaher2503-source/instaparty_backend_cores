<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Shared\Http\Resources\ChangeRequestResource;
use Illuminate\Http\Request;

class VendorChangeRequestListController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $status = $request->query('status');

        $query = ChangeRequest::query()
            ->where(function ($q) use ($request) {
                $user = $request->user();
                if ($user && $user->vendor_profile_id) {
                    $q->forSubject('vendor_profile', $user->vendor_profile_id);
                }
            });

        if ($status) {
            $query->where('status', $status);
        }

        $changeRequests = $query->paginate($perPage);

        return response()->json([
            'data' => ChangeRequestResource::collection($changeRequests),
            'meta' => [
                'current_page' => $changeRequests->currentPage(),
                'per_page' => $changeRequests->perPage(),
                'total' => $changeRequests->total(),
            ],
            'errors' => [],
        ]);
    }
}
