<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use App\Modules\Shared\Domain\Models\DesignToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ActivateDesignTokenAction
{
    public function execute(DesignToken $token): DesignToken
    {
        return DB::transaction(function () use ($token): DesignToken {
            DesignToken::query()
                ->where('id', '!=', $token->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_by' => Auth::id(),
                ]);

            $token->is_active = true;
            $token->updated_by = Auth::id();
            $token->save();

            DB::afterCommit(function () use ($token): void {
                Cache::forget('theme:tokens:active');
                event(new PublicThemeChanged(reason: 'tokens.activated', publicId: $token->public_id));
            });

            return $token->fresh();
        });
    }
}
