<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Models\DesignToken;
use App\Modules\Shared\Domain\Schemas\DesignTokenSchema;
use Illuminate\Support\Facades\Cache;

class GetActiveDesignTokensAction
{
    public const CACHE_KEY = 'theme:tokens:active';
    public const CACHE_TTL_SECONDS = 300;

    public function execute(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $active = DesignToken::query()->active()->first();

            if ($active === null) {
                return [
                    'public_id' => null,
                    'name' => 'default',
                    'tokens' => DesignTokenSchema::default(),
                    'updated_at' => null,
                ];
            }

            return [
                'public_id' => $active->public_id,
                'name' => $active->name,
                'tokens' => $active->tokens,
                'updated_at' => $active->updated_at?->toISOString(),
            ];
        });
    }
}
