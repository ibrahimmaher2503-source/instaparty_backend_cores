<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum CmsSlug: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
    case About = 'about';
    case Contact = 'contact';
}
