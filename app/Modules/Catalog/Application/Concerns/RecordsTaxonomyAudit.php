<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Concerns;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait RecordsTaxonomyAudit
{
    /** @param  array<string, mixed>  $changes */
    protected function recordAudit(
        Model $subject,
        string $action,
        ?User $actor = null,
        array $changes = [],
    ): void {
        DB::table('audit_logs')->insert([
            'public_id'      => (string) Str::ulid(),
            'auditable_type' => $subject::class,
            'auditable_id'   => $subject->getKey(),
            'user_id'        => $actor?->id,
            'action'         => $action,
            'changes'        => json_encode($changes, JSON_UNESCAPED_UNICODE),
            'created_at'     => now(),
        ]);
    }
}
