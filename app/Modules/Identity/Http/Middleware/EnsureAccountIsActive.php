<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->status === 'suspended') {
            return response()->json([
                'errors' => [[
                    'code' => 'account_suspended',
                    'message' => __('identity.account_suspended'),
                ]],
            ], 403);
        }

        return $next($request);
    }
}
