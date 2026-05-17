<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum NavigationTargetType: string
{
    case InternalPath = 'internal_path';
    case ExternalUrl = 'external_url';
    case CmsPage = 'cms_page';
    case Category = 'category';
    case Occasion = 'occasion';

    public function label(): string
    {
        return match ($this) {
            self::InternalPath => 'Internal path',
            self::ExternalUrl => 'External URL',
            self::CmsPage => 'CMS page',
            self::Category => 'Category',
            self::Occasion => 'Occasion',
        };
    }
}
