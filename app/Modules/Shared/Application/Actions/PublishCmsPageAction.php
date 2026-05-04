<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Models\CmsPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishCmsPageAction
{
    public function execute(CmsPage $page): CmsPage
    {
        $this->validate($page);

        return DB::transaction(function () use ($page): CmsPage {
            $page->is_published = true;
            $page->published_at = now();
            $page->updated_by = Auth::id();
            $page->save();

            return $page;
        });
    }

    private function validate(CmsPage $page): void
    {
        $errors = [];

        if (blank($page->getTranslation('body', 'en', useFallbackLocale: false))) {
            $errors['body_en'] = [__('cms.validation.body_en_required')];
        }

        if (blank($page->getTranslation('body', 'ar', useFallbackLocale: false))) {
            $errors['body_ar'] = [__('cms.validation.body_ar_required')];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
