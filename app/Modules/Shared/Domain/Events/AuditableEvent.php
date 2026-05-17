<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Events;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Marker contract for domain events that must be persisted to `audit_logs`.
 *
 * A single, uniform audit listener can subscribe to any event implementing this
 * contract and extract the four fields needed to write one `audit_logs` row:
 *
 *  - {@see auditable()}  → the Eloquent record the event happened to.
 *  - {@see actor()}      → the user who triggered it, or `null` for system actors.
 *  - {@see action()}     → a stable string key (e.g. `chat.frozen`).
 *  - {@see changes()}    → an associative JSON-serializable payload describing
 *                          what changed, including bilingual reasons / notes.
 *
 * Implementations should be `final readonly` PHP 8.3 classes and must be
 * dispatched only after `DB::transaction` has committed (Constitution §VI).
 */
interface AuditableEvent
{
    public function auditable(): Model;

    public function actor(): ?User;

    public function action(): string;

    /**
     * @return array<string, mixed>
     */
    public function changes(): array;
}
