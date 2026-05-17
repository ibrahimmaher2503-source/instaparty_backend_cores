<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Enums\HomeBlockType;
use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use App\Modules\Shared\Domain\Models\HomeBlock;
use App\Modules\Shared\Domain\Schemas\HomeBlockPayloadSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SaveHomeBlockAction
{
    public function execute(?HomeBlock $block, array $data): HomeBlock
    {
        $type = HomeBlockType::from((string) $data['block_type']);
        $validatedPayload = HomeBlockPayloadSchema::validate($type, (array) ($data['payload'] ?? []));

        return DB::transaction(function () use ($block, $type, $validatedPayload, $data): HomeBlock {
            $block ??= new HomeBlock;
            $block->fill([
                'block_type' => $type->value,
                'name' => (string) $data['name'],
                'position' => (int) ($data['position'] ?? 0),
                'is_visible' => (bool) ($data['is_visible'] ?? true),
                'payload' => $validatedPayload,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'updated_by' => Auth::id(),
            ])->save();

            DB::afterCommit(function () use ($block): void {
                Cache::forget('theme:homepage:en');
                Cache::forget('theme:homepage:ar');
                event(new PublicThemeChanged(reason: 'home_blocks.saved', publicId: $block->public_id));
            });

            return $block->fresh();
        });
    }
}
