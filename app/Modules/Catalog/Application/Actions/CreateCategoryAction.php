<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Application\DTOs\CategoryDTO;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCategoryAction
{
    use RecordsTaxonomyAudit;

    public function execute(CategoryDTO $dto, ?User $actor = null): Category
    {
        return DB::transaction(function () use ($dto, $actor): Category {
            $category = Category::create([
                'public_id' => (string) Str::ulid(),
                ...$dto->toAttributes(),
            ]);

            $this->recordAudit($category, 'category_created', $actor, $dto->toAttributes());

            return $category;
        });
    }
}
