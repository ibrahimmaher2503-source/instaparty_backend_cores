<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Application\DTOs\CategoryFieldSchemaDTO;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCategoryFieldSchemaAction
{
    use RecordsTaxonomyAudit;

    public function execute(
        CategoryFieldSchema $schema,
        CategoryFieldSchemaDTO $dto,
        ?User $actor = null,
    ): CategoryFieldSchema {
        return DB::transaction(function () use ($schema, $dto, $actor): CategoryFieldSchema {
            $schema->update($dto->toAttributes());

            $this->recordAudit($schema, 'category_field_schema_updated', $actor, $dto->toAttributes());

            return $schema->refresh();
        });
    }
}
