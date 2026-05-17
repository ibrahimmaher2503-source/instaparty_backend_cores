<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Enums\ServiceFieldClassification;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceChangeRequestItem>
 */
class ServiceChangeRequestItemFactory extends Factory
{
    protected $model = ServiceChangeRequestItem::class;

    public function definition(): array
    {
        return [
            'service_change_request_id' => ServiceChangeRequest::factory(),
            'field_path'                => 'base_price_minor',
            'field_classification'      => ServiceFieldClassification::Shared,
            'before_value'              => 10000,
            'after_value'               => 12500,
        ];
    }
}
