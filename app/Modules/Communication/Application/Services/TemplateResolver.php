<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Services;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;

class TemplateResolver
{
    public function resolve(
        string $eventKey,
        NotificationChannel $channel,
        NotificationAudience $audience,
        string $locale = 'en'
    ): ResolvedTemplate {
        $template = NotificationTemplate::query()
            ->active()
            ->forEventChannelAudience($eventKey, $channel, $audience)
            ->first();

        if ($template === null) {
            throw new TemplateNotFoundException(
                "No active template for event_key={$eventKey} channel={$channel->value} audience={$audience->value}"
            );
        }

        return new ResolvedTemplate(
            templateId: $template->id,
            body: $template->getTranslation('body', $locale, false) ?: $template->getTranslation('body', 'en', false),
            subject: $template->subject ? ($template->getTranslation('subject', $locale, false) ?: $template->getTranslation('subject', 'en', false)) : null,
            variables: $template->variables ?? [],
        );
    }
}
