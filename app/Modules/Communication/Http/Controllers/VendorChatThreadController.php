<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 11.1 — the vendor's chat threads (Firestore metadata
 * mirror, spec 056). Message content lives in Firestore; this endpoint
 * gives mobile the thread index. Send/read-state stay client-side
 * (Firestore-first — 11.2 upload-url and 11.3 mark-read intentionally
 * not REST endpoints).
 *
 * @group Chat
 */
class VendorChatThreadController
{
    public function __invoke(Request $request): JsonResponse
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $page = ChatThread::query()
            ->where('vendor_profile_id', $vendor->id)
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return ApiResponse::success(
            collect($page->items())->map(fn (ChatThread $thread): array => [
                'public_id' => $thread->public_id,
                'firestore_thread_id' => $thread->firestore_thread_id,
                'booking_public_id' => $thread->booking?->public_id,
                'status' => $thread->status,
                'frozen' => $thread->frozen_at !== null,
                'created_at' => $thread->created_at?->toIso8601String(),
            ])->values(),
            ['next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages()],
        );
    }
}
