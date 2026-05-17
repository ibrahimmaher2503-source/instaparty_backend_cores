<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Application\DTOs\CategoryFieldSchemaDTO;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCategoryFieldSchemaAction
{
    use RecordsTaxonomyAudit;

    public function execute(CategoryFieldSchemaDTO $dto, ?User $actor = null): CategoryFieldSchema
    {
        return DB::transaction(function () use ($dto, $actor): CategoryFieldSchema {
            $schema = CategoryFieldSchema::create($dto->toAttributes());

            $this->recordAudit($schema, 'category_field_schema_created', $actor, $dto->toAttributes());

            return $schema;
        });
    }
}
