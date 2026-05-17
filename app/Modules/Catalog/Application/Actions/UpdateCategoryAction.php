<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Application\DTOs\CategoryDTO;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCategoryAction
{
    use RecordsTaxonomyAudit;

    public function execute(Category $category, CategoryDTO $dto, ?User $actor = null): Category
    {
        return DB::transaction(function () use ($category, $dto, $actor): Category {
            $category->update($dto->toAttributes());

            $this->recordAudit($category, 'category_updated', $actor, $dto->toAttributes());

            return $category->refresh();
        });
    }
}
