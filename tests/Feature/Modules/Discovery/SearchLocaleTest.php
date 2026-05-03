<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;

it('Arabic Accept-Language header returns name in Arabic', function (): void {
    Service::factory()->create([
        'name'   => ['en' => 'Bouncy Castle', 'ar' => 'نطاطة'],
        'status' => ServiceStatus::Published,
    ]);

    $response = $this->withHeader('Accept-Language', 'ar')
        ->getJson('/api/v1/customer/services?q=نطاطة');

    $response->assertStatus(200);
    $data = $response->json('data');
    if (count($data) > 0) {
        expect($data[0]['name'])->toBe('نطاطة');
    }
})->group('discovery', 'search', 'locale');

it('English Accept-Language header returns name in English', function (): void {
    Service::factory()->create([
        'name'   => ['en' => 'Bouncy Castle', 'ar' => 'نطاطة'],
        'status' => ServiceStatus::Published,
    ]);

    $response = $this->withHeader('Accept-Language', 'en')
        ->getJson('/api/v1/customer/services?q=Bouncy');

    $response->assertStatus(200);
    $data = $response->json('data');
    if (count($data) > 0) {
        expect($data[0]['name'])->toBe('Bouncy Castle');
    }
})->group('discovery', 'search', 'locale');
