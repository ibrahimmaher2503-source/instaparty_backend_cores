<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\ServiceRejected;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectServiceAction
{
    /**
     * @param  array{en?: string|null, ar?: string|null}  $notes
     */
    public function execute(Service $service, array $notes, User $admin): Service
    {
        $service->refresh();
        $notes = $this->normalizeNotes($notes);

        if (! $service->status->canTransitionTo(ServiceStatus::Rejected)) {
            throw ValidationException::withMessages([
                'status' => __('catalog.moderation_invalid_transition'),
            ]);
        }

        if (! app(ServicePolicy::class)->reject($admin, $service)) {
            throw new AuthorizationException(__('catalog.moderation_not_allowed'));
        }

        return DB::transaction(function () use ($service, $notes, $admin): Service {
            $service->update([
                'status' => ServiceStatus::Rejected,
                'moderation_notes' => $notes,
                'moderated_at' => now(),
                'moderated_by' => $admin->id,
            ]);

            DB::afterCommit(fn () => event(new ServiceRejected($service->refresh())));

            return $service;
        });
    }

    /**
     * @param  array{en?: string|null, ar?: string|null}  $notes
     * @return array{en: string, ar: string}
     */
    private function normalizeNotes(array $notes): array
    {
        $normalized = [
            'en' => trim((string) ($notes['en'] ?? '')),
            'ar' => trim((string) ($notes['ar'] ?? '')),
        ];

        $messages = [];

        foreach ($normalized as $locale => $value) {
            if ($value === '') {
                $messages["reason.{$locale}"] = __('validation.required', ['attribute' => __("catalog.reject_reason_{$locale}")]);
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $normalized;
    }
}
