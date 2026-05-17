<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Schemas;

use App\Modules\Shared\Domain\Enums\HomeBlockType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HomeBlockPayloadSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function validate(HomeBlockType $type, array $payload): array
    {
        $rules = match ($type) {
            HomeBlockType::HeroCarousel => [
                'slides' => ['required', 'array', 'min:1', 'max:10'],
                'slides.*.image_url' => ['required', 'string', 'url', 'max:1024'],
                'slides.*.headline.en' => ['required', 'string', 'max:160'],
                'slides.*.headline.ar' => ['required', 'string', 'max:160'],
                'slides.*.sub.en' => ['nullable', 'string', 'max:240'],
                'slides.*.sub.ar' => ['nullable', 'string', 'max:240'],
                'slides.*.cta_label.en' => ['nullable', 'string', 'max:60'],
                'slides.*.cta_label.ar' => ['nullable', 'string', 'max:60'],
                'slides.*.cta_url' => ['nullable', 'string', 'max:512'],
            ],
            HomeBlockType::FeaturedServices => [
                'title.en' => ['required', 'string', 'max:120'],
                'title.ar' => ['required', 'string', 'max:120'],
                'service_public_ids' => ['required', 'array', 'min:1', 'max:24'],
                'service_public_ids.*' => ['string', 'size:26'],
            ],
            HomeBlockType::FeaturedOccasions, HomeBlockType::FeaturedCategories => [
                'title.en' => ['required', 'string', 'max:120'],
                'title.ar' => ['required', 'string', 'max:120'],
                'public_ids' => ['required', 'array', 'min:1', 'max:24'],
                'public_ids.*' => ['string', 'size:26'],
            ],
            HomeBlockType::VendorSpotlight => [
                'title.en' => ['required', 'string', 'max:120'],
                'title.ar' => ['required', 'string', 'max:120'],
                'vendor_public_id' => ['required', 'string', 'size:26'],
            ],
            HomeBlockType::CtaBanner => [
                'headline.en' => ['required', 'string', 'max:160'],
                'headline.ar' => ['required', 'string', 'max:160'],
                'image_url' => ['nullable', 'string', 'url', 'max:1024'],
                'cta_label.en' => ['required', 'string', 'max:60'],
                'cta_label.ar' => ['required', 'string', 'max:60'],
                'cta_url' => ['required', 'string', 'max:512'],
            ],
            HomeBlockType::TextImageSplit => [
                'headline.en' => ['required', 'string', 'max:160'],
                'headline.ar' => ['required', 'string', 'max:160'],
                'body.en' => ['required', 'string', 'max:2000'],
                'body.ar' => ['required', 'string', 'max:2000'],
                'image_url' => ['required', 'string', 'url', 'max:1024'],
                'image_side' => ['required', 'string', 'in:left,right'],
            ],
            HomeBlockType::Testimonials => [
                'title.en' => ['required', 'string', 'max:120'],
                'title.ar' => ['required', 'string', 'max:120'],
                'items' => ['required', 'array', 'min:1', 'max:12'],
                'items.*.quote.en' => ['required', 'string', 'max:600'],
                'items.*.quote.ar' => ['required', 'string', 'max:600'],
                'items.*.author' => ['required', 'string', 'max:120'],
                'items.*.avatar_url' => ['nullable', 'string', 'url', 'max:1024'],
            ],
            HomeBlockType::LoyaltyPromo => [
                'headline.en' => ['required', 'string', 'max:160'],
                'headline.ar' => ['required', 'string', 'max:160'],
                'body.en' => ['nullable', 'string', 'max:600'],
                'body.ar' => ['nullable', 'string', 'max:600'],
                'cta_url' => ['nullable', 'string', 'max:512'],
            ],
        };

        $validator = Validator::make($payload, $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }
}
