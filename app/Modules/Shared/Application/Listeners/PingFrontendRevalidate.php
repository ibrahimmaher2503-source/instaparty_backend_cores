<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Listeners;

use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PingFrontendRevalidate implements ShouldQueue
{
    public function handle(PublicThemeChanged $event): void
    {
        $url = (string) config('services.frontend.revalidate_url');
        $secret = (string) config('services.frontend.revalidate_secret');

        if ($url === '' || $secret === '') {
            return;
        }

        $response = Http::timeout(5)
            ->withHeaders(['X-Revalidate-Secret' => $secret])
            ->asJson()
            ->post($url, [
                'reason' => $event->reason,
                'public_id' => $event->publicId,
                'paths' => ['/'],
                'tags' => ['theme', 'branding', 'menus', 'homepage'],
            ]);

        if ($response->failed()) {
            Log::warning('Frontend revalidate ping failed', [
                'status' => $response->status(),
                'reason' => $event->reason,
            ]);
        }
    }
}
