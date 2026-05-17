<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Models\NavigationMenu;
use App\Modules\Shared\Domain\Models\NavigationMenuItem;
use Illuminate\Support\Facades\Cache;

class GetNavigationMenuAction
{
    public const CACHE_TTL_SECONDS = 300;

    public function execute(NavigationSlot $slot, string $locale): array
    {
        return Cache::remember(
            'theme:menus:'.$slot->value.':'.$locale,
            self::CACHE_TTL_SECONDS,
            function () use ($slot, $locale): array {
                $menu = NavigationMenu::query()
                    ->where('slot', $slot->value)
                    ->with(['items' => fn ($q) => $q->where('is_visible', true)->orderBy('position')])
                    ->first();

                if ($menu === null) {
                    return ['slot' => $slot->value, 'name' => $slot->label(), 'items' => []];
                }

                $byParent = $menu->items->groupBy('parent_id');

                $build = function (?int $parentId) use (&$build, $byParent, $locale): array {
                    return ($byParent->get($parentId) ?? collect())
                        ->map(fn (NavigationMenuItem $item): array => [
                            'public_id' => $item->public_id,
                            'label' => $item->getTranslation('label', $locale, useFallbackLocale: true),
                            'target_type' => $item->target_type->value,
                            'target_value' => $item->target_value,
                            'icon' => $item->icon,
                            'opens_in_new_tab' => $item->opens_in_new_tab,
                            'children' => $build($item->id),
                        ])
                        ->values()
                        ->all();
                };

                return [
                    'slot' => $menu->slot->value,
                    'name' => $menu->name,
                    'items' => $build(null),
                ];
            },
        );
    }
}
