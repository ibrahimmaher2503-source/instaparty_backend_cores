<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceDigitalDetail>
 */
class ServiceDigitalDetailFactory extends Factory
{
    protected $model = ServiceDigitalDetail::class;

    public function definition(): array
    {
        return [
            'delivery_method'              => $this->faker->randomElement(['email', 'sms', 'whatsapp', 'link']),
            'has_expiry'                   => $this->faker->boolean(),
            'expiry_days_after_purchase'   => $this->faker->numberBetween(7, 365),
            'is_refundable_after_delivery' => false,
            'redemption_url_template'      => null,
            'code_pool_id'                 => null,
        ];
    }
}
