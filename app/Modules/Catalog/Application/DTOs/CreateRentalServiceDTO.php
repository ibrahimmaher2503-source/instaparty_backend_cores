<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

use App\Modules\Catalog\Http\Requests\CreateRentalServiceRequest;

readonly class CreateRentalServiceDTO
{
    public function __construct(
        public int $vendorProfileId,
        public int $categoryId,
        /** @var array{en: string, ar: string} */
        public array $name,
        /** @var array{en: string, ar: string} */
        public array $shortDescription,
        public int $basePriceMinor,
        public bool $requiresElectricity,
        public bool $requiresOutdoorSpace,
        public int $defaultRentalDurationHours,
        public ?int $setupTimeMinutes,
        public ?int $teardownTimeMinutes,
        public ?int $securityDepositMinor,
        public ?int $minimumSpaceSqm,
    ) {}

    public static function fromRequest(CreateRentalServiceRequest $request, int $vendorProfileId): self
    {
        return new self(
            vendorProfileId: $vendorProfileId,
            categoryId: $request->integer('category_id'),
            name: $request->array('name'),
            shortDescription: $request->array('short_description'),
            basePriceMinor: $request->integer('base_price_minor'),
            requiresElectricity: $request->boolean('requires_electricity'),
            requiresOutdoorSpace: $request->boolean('requires_outdoor_space'),
            defaultRentalDurationHours: $request->integer('default_rental_duration_hours'),
            setupTimeMinutes: $request->filled('setup_time_minutes') ? $request->integer('setup_time_minutes') : null,
            teardownTimeMinutes: $request->filled('teardown_time_minutes') ? $request->integer('teardown_time_minutes') : null,
            securityDepositMinor: $request->filled('security_deposit_minor') ? $request->integer('security_deposit_minor') : null,
            minimumSpaceSqm: $request->filled('minimum_space_sqm') ? $request->integer('minimum_space_sqm') : null,
        );
    }
}
