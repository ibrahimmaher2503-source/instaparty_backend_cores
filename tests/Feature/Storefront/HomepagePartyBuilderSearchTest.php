<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Geography\Domain\Models\City;
use Illuminate\Support\Str;

it('renders a functional homepage search contract without the closing planning panel', function (string $locale): void {
    $occasion = Occasion::factory()->active()->create([
        'code' => 'birthday',
        'name' => ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
    ]);
    $city = City::factory()->create([
        'name' => ['en' => 'Nasr City', 'ar' => 'مدينة نصر'],
    ]);

    $this->get('/'.$locale)
        ->assertOk()
        ->assertSee('action="'.url('/'.$locale.'/search').'"', false)
        ->assertSee('name="occasion"', false)
        ->assertSee('value="'.$occasion->code.'"', false)
        ->assertSee('name="city_public_id"', false)
        ->assertSee('value="'.$city->public_id.'"', false)
        ->assertSee('name="event_date"', false)
        ->assertDontSee('sf-final-section', false);
})->with(['en', 'ar']);

it('filters blocked rentals by event date while retaining sale and digital services', function (): void {
    config()->set('scout.driver', 'database');

    $eventDate = now('UTC')->addDays(10)->startOfDay();
    $blockedRental = Service::factory()->rental()->published()->create([
        'name' => ['en' => 'Blocked rental', 'ar' => 'إيجار محجوب'],
    ]);
    $openRental = Service::factory()->rental()->published()->create([
        'name' => ['en' => 'Open rental', 'ar' => 'إيجار متاح'],
    ]);
    $sale = Service::factory()->sale()->published()->create([
        'name' => ['en' => 'Available sale item', 'ar' => 'منتج بيع متاح'],
    ]);
    $digital = Service::factory()->digital()->published()->create([
        'name' => ['en' => 'Available digital item', 'ar' => 'منتج رقمي متاح'],
    ]);

    ServiceAvailabilityBlock::query()->create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $blockedRental->id,
        'starts_at' => $eventDate->copy()->startOfDay(),
        'ends_at' => $eventDate->copy()->endOfDay(),
        'reason' => ['en' => 'Already booked', 'ar' => 'محجوز مسبقًا'],
    ]);

    $this->get('/en/search?event_date='.$eventDate->toDateString())
        ->assertOk()
        ->assertDontSee($blockedRental->getTranslation('name', 'en'))
        ->assertSee($openRental->getTranslation('name', 'en'))
        ->assertSee($sale->getTranslation('name', 'en'))
        ->assertSee($digital->getTranslation('name', 'en'))
        ->assertSee('name="event_date"', false)
        ->assertSee('value="'.$eventDate->toDateString().'"', false);

    expect($openRental->product_type)->toBe(ProductType::Rental)
        ->and($sale->product_type)->toBe(ProductType::Sale)
        ->and($digital->product_type)->toBe(ProductType::Digital);
});
