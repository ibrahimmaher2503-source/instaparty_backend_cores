<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCategoryAction
{
    use RecordsTaxonomyAudit;

    public function execute(Category $category, ?User $actor = null): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => __('catalog.category_has_children'),
            ]);
        }

        DB::transaction(function () use ($category, $actor): void {
            $this->recordAudit($category, 'category_deleted', $actor, ['code' => $category->code]);
            $category->delete();
        });
    }
}
