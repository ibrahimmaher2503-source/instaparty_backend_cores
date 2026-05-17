<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use App\Modules\Shared\Domain\Models\DesignToken;
use App\Modules\Shared\Domain\Schemas\DesignTokenSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SaveDesignTokenAction
{
    public function execute(?DesignToken $token, string $name, array $tokens): DesignToken
    {
        $validated = DesignTokenSchema::validate($tokens);

        return DB::transaction(function () use ($token, $name, $validated): DesignToken {
            $token ??= new DesignToken;

            $token->name = $name;
            $token->tokens = $validated;
            $token->updated_by = Auth::id();
            if (! $token->exists) {
                $token->created_by = Auth::id();
            }
            $token->save();

            DB::afterCommit(function () use ($token): void {
                if ($token->is_active) {
                    Cache::forget('theme:tokens:active');
                    event(new PublicThemeChanged(reason: 'tokens.saved', publicId: $token->public_id));
                }
            });

            return $token;
        });
    }
}
