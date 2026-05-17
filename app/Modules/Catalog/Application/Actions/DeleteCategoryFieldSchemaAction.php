<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteCategoryFieldSchemaAction
{
    use RecordsTaxonomyAudit;

    public function execute(CategoryFieldSchema $schema, ?User $actor = null): void
    {
        DB::transaction(function () use ($schema, $actor): void {
            $this->recordAudit(
                $schema,
                'category_field_schema_deleted',
                $actor,
                ['category_id' => $schema->category_id, 'field_key' => $schema->field_key],
            );
            $schema->delete();
        });
    }
}
