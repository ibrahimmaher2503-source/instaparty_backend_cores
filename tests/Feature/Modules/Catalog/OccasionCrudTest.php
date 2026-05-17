<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\CreateOccasionAction;
use App\Modules\Catalog\Application\Actions\DeleteOccasionAction;
use App\Modules\Catalog\Application\Actions\UpdateOccasionAction;
use App\Modules\Catalog\Application\DTOs\OccasionDTO;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->admin = User::factory()->phoneVerified()->superAdmin()->create();
});

it('creates an occasion via CreateOccasionAction with bilingual name', function (): void {
    $dto = new OccasionDTO(
        code: 'test-bday',
        name: ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
        description: ['en' => 'Birthday parties', 'ar' => 'حفلات أعياد الميلاد'],
        sortOrder: 1,
        isActive: true,
    );

    $occasion = app(CreateOccasionAction::class)->execute($dto, $this->admin);

    expect($occasion)
        ->code->toBe('test-bday')
        ->getTranslation('name', 'en')->toBe('Birthday')
        ->getTranslation('name', 'ar')->toBe('عيد ميلاد')
        ->public_id->not->toBeNull();

    expect(DB::table('audit_logs')
        ->where('action', 'occasion_created')
        ->where('user_id', $this->admin->id)
        ->count(),
    )->toBe(1);
})->group('catalog', 'taxonomy', 'occasions');

it('updates an occasion via UpdateOccasionAction and writes audit', function (): void {
    $occasion = app(CreateOccasionAction::class)->execute(new OccasionDTO(
        code: 'test-engagement',
        name: ['en' => 'Engagement', 'ar' => 'خطوبة'],
    ), $this->admin);

    $updated = app(UpdateOccasionAction::class)->execute(
        $occasion,
        new OccasionDTO(
            code: 'test-engagement',
            name: ['en' => 'Engagement Party', 'ar' => 'حفل خطوبة'],
            sortOrder: 5,
        ),
        $this->admin,
    );

    expect($updated->getTranslation('name', 'en'))->toBe('Engagement Party');
    expect($updated->sort_order)->toBe(5);
    expect(DB::table('audit_logs')->where('action', 'occasion_updated')->count())->toBe(1);
})->group('catalog', 'taxonomy', 'occasions');

it('soft-deletes an occasion via DeleteOccasionAction', function (): void {
    $occasion = app(CreateOccasionAction::class)->execute(new OccasionDTO(
        code: 'test-wedding',
        name: ['en' => 'Wedding', 'ar' => 'زفاف'],
    ), $this->admin);

    app(DeleteOccasionAction::class)->execute($occasion, $this->admin);

    expect(Occasion::query()->find($occasion->id))->toBeNull();
    expect(Occasion::withTrashed()->find($occasion->id))->not->toBeNull();
    expect(DB::table('audit_logs')->where('action', 'occasion_deleted')->count())->toBe(1);
})->group('catalog', 'taxonomy', 'occasions');

it('rejects duplicate occasion code', function (): void {
    app(CreateOccasionAction::class)->execute(new OccasionDTO(
        code: 'test-bday',
        name: ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
    ), $this->admin);

    expect(fn () => app(CreateOccasionAction::class)->execute(new OccasionDTO(
        code: 'test-bday',
        name: ['en' => 'Other', 'ar' => 'آخر'],
    ), $this->admin))->toThrow(\Illuminate\Database\QueryException::class);
})->group('catalog', 'taxonomy', 'occasions');

it('DTO fromArray populates bilingual JSON correctly', function (): void {
    $dto = OccasionDTO::fromArray([
        'code' => 'test-graduation',
        'name' => ['en' => 'Graduation', 'ar' => 'تخرج'],
        'is_active' => true,
        'sort_order' => 7,
    ]);

    $occasion = app(CreateOccasionAction::class)->execute($dto, $this->admin);

    $reloaded = Occasion::query()->where('code', 'test-graduation')->firstOrFail();
    expect($reloaded->getTranslation('name', 'ar'))->toBe('تخرج');
    expect($reloaded->is_active)->toBeTrue();
})->group('catalog', 'taxonomy', 'occasions');
