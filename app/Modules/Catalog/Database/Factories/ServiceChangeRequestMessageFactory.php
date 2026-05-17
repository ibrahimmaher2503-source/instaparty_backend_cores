<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequestMessage;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceChangeRequestMessage>
 */
class ServiceChangeRequestMessageFactory extends Factory
{
    protected $model = ServiceChangeRequestMessage::class;

    public function definition(): array
    {
        return [
            'service_change_request_id' => ServiceChangeRequest::factory(),
            'author_user_id'            => User::factory(),
            'author_role'               => 'admin',
            'body'                      => [
                'en' => 'Why are you raising the price?',
                'ar' => 'لماذا ترفع السعر؟',
            ],
            'clarification_round'       => 1,
        ];
    }
}
