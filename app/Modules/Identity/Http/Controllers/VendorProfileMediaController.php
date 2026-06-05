<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Actions\UploadMediaAction;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 2.3–2.6 (logo/cover — path-based, the columns customer
 * resources already consume) + 2.9/2.10 (portfolio — ADR-0047 public
 * media collection).
 */
class VendorProfileMediaController
{
    private const BRANDING = ['logo' => 'logo_path', 'cover' => 'cover_path'];

    /** 2.3 / 2.4 — upload logo or cover. */
    public function uploadBranding(Request $request, string $kind): JsonResponse
    {
        $column = self::BRANDING[$kind] ?? abort(404);

        $request->validate(['file' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120']]);

        $vendor = $this->vendorProfile($request);

        $path = DB::transaction(function () use ($request, $vendor, $column, $kind): string {
            $old = $vendor->{$column};
            $path = $request->file('file')->store("vendors/{$vendor->id}/{$kind}", 'public');
            $vendor->forceFill([$column => $path])->save();

            if ($old !== null) {
                Storage::disk('public')->delete($old);
            }

            return $path;
        });

        return ApiResponse::success([$kind.'_url' => Storage::url($path)], [], 201);
    }

    /** 2.5 / 2.6 — remove logo or cover. */
    public function deleteBranding(Request $request, string $kind): JsonResponse
    {
        $column = self::BRANDING[$kind] ?? abort(404);

        $vendor = $this->vendorProfile($request);

        DB::transaction(function () use ($vendor, $column): void {
            $old = $vendor->{$column};
            $vendor->forceFill([$column => null])->save();

            if ($old !== null) {
                Storage::disk('public')->delete($old);
            }
        });

        return ApiResponse::success(null);
    }

    /** 2.9 — upload portfolio photos. */
    public function uploadPortfolio(Request $request, UploadMediaAction $upload): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $uploaded = $upload->execute($this->vendorProfile($request), 'portfolio', (array) $request->file('files', []));

        return ApiResponse::success($uploaded, ['collection' => 'portfolio'], 201);
    }

    /** Portfolio listing for the management screen. */
    public function listPortfolio(Request $request): JsonResponse
    {
        $media = $this->vendorProfile($request)->getMedia('portfolio')->map(fn ($m): array => [
            'public_id' => $m->uuid,
            'url' => $m->getUrl(),
            'created_at' => $m->created_at?->toIso8601String(),
        ])->values();

        return ApiResponse::success($media);
    }

    /** 2.10 — remove one portfolio photo (own only). */
    public function deletePortfolio(Request $request, string $mediaPublicId): JsonResponse
    {
        $vendor = $this->vendorProfile($request);

        $media = $vendor->getMedia('portfolio')->firstWhere('uuid', $mediaPublicId);
        abort_if($media === null, 404, 'Portfolio photo not found.');

        DB::transaction(fn () => $media->delete());

        return ApiResponse::success(null);
    }

    private function vendorProfile(Request $request): VendorProfile
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return $vendor;
    }
}
