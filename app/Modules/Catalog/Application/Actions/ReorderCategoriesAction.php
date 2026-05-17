<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderCategoriesAction
{
    use RecordsTaxonomyAudit;

    /**
     * Atomically rewrite sort_order for the supplied set of category public_ids.
     * All categories must share the same parent_id (or all be roots).
     *
     * @param  array<int, string>  $orderedPublicIds  Ordered list of public_ids; index = new sort_order.
     */
    public function execute(array $orderedPublicIds, ?int $parentId = null, ?User $actor = null): void
    {
        if ($orderedPublicIds === []) {
            return;
        }

        DB::transaction(function () use ($orderedPublicIds, $parentId, $actor): void {
            $categories = Category::query()
                ->whereIn('public_id', $orderedPublicIds)
                ->get()
                ->keyBy('public_id');

            if ($categories->count() !== count($orderedPublicIds)) {
                throw ValidationException::withMessages([
                    'public_ids' => __('catalog.reorder_unknown_categories'),
                ]);
            }

            $mismatched = $categories->first(fn (Category $c) => $c->parent_id !== $parentId);
            if ($mismatched !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => __('catalog.reorder_parent_mismatch'),
                ]);
            }

            foreach ($orderedPublicIds as $sortOrder => $publicId) {
                $categories[$publicId]->update(['sort_order' => $sortOrder]);
            }

            $first = $categories->first();
            if ($first !== null) {
                $this->recordAudit(
                    $first,
                    'categories_reordered',
                    $actor,
                    ['parent_id' => $parentId, 'ordered_public_ids' => $orderedPublicIds],
                );
            }
        });
    }
}
