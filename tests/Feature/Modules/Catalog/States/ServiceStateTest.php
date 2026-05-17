<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ArchivedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\ChangesRequestedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PendingReviewState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\RejectedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ApproveServiceTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ArchiveServiceTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\RejectServiceTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\RequestServiceChangesTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ResubmitAfterChangesTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\SubmitForReviewTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\UnarchiveServiceTransition;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

uses(RefreshDatabase::class)->group('catalog', 'service-states');

// =========================================================================
// Helpers
// =========================================================================

function makeVendorUserAndService(string $status = 'draft'): array
{
    $user = User::factory()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $service = Service::factory()->for($vendor, 'vendor')->create(['status' => $status]);

    return compact('user', 'vendor', 'service');
}

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->admin = User::factory()->asAdmin()->create();
});

// =========================================================================
// T033: Valid transitions — each writes 1 state_transitions row
// =========================================================================

it('vendor can submit draft service for review', function (): void {
    ['user' => $user, 'service' => $service] = makeVendorUserAndService('draft');

    $this->actingAs($user);

    $service->status->transition(new SubmitForReviewTransition($service, $user->id));

    $service->refresh();

    expect($service->status)->toBeInstanceOf(PendingReviewState::class);

    expect(\DB::table('state_transitions')
        ->where('transitionable_type', Service::class)
        ->where('transitionable_id', $service->id)
        ->where('from_state', DraftState::$name)
        ->where('to_state', PendingReviewState::$name)
        ->exists()
    )->toBeTrue();
})->group('valid-transitions');

it('admin can approve pending service', function (): void {
    ['service' => $service] = makeVendorUserAndService('pending_review');

    $this->actingAs($this->admin);

    $service->status->transition(new ApproveServiceTransition($service, $this->admin->id));

    $service->refresh();

    expect($service->status)->toBeInstanceOf(PublishedState::class);

    expect(\DB::table('state_transitions')
        ->where('transitionable_type', Service::class)
        ->where('transitionable_id', $service->id)
        ->where('from_state', PendingReviewState::$name)
        ->where('to_state', PublishedState::$name)
        ->exists()
    )->toBeTrue();
})->group('valid-transitions');

it('admin can reject pending service', function (): void {
    ['service' => $service] = makeVendorUserAndService('pending_review');

    $this->actingAs($this->admin);

    $service->status->transition(new RejectServiceTransition($service, $this->admin->id, ['en' => 'Low quality', 'ar' => 'جودة منخفضة']));

    $service->refresh();

    expect($service->status)->toBeInstanceOf(RejectedState::class);

    expect(\DB::table('state_transitions')
        ->where('transitionable_type', Service::class)
        ->where('transitionable_id', $service->id)
        ->where('from_state', PendingReviewState::$name)
        ->where('to_state', RejectedState::$name)
        ->exists()
    )->toBeTrue();
})->group('valid-transitions');

it('admin can request changes on pending service', function (): void {
    ['service' => $service] = makeVendorUserAndService('pending_review');

    $this->actingAs($this->admin);

    $service->status->transition(new RequestServiceChangesTransition($service, $this->admin->id, ['en' => 'Fix photos', 'ar' => 'أصلح الصور']));

    $service->refresh();

    expect($service->status)->toBeInstanceOf(ChangesRequestedState::class);

    expect(\DB::table('state_transitions')
        ->where('transitionable_type', Service::class)
        ->where('transitionable_id', $service->id)
        ->where('from_state', PendingReviewState::$name)
        ->where('to_state', ChangesRequestedState::$name)
        ->exists()
    )->toBeTrue();
})->group('valid-transitions');

it('vendor can resubmit after changes requested', function (): void {
    ['user' => $user, 'service' => $service] = makeVendorUserAndService('changes_requested');

    $this->actingAs($user);

    $service->status->transition(new ResubmitAfterChangesTransition($service, $user->id));

    $service->refresh();

    expect($service->status)->toBeInstanceOf(PendingReviewState::class);

    expect(\DB::table('state_transitions')
        ->where('transitionable_type', Service::class)
        ->where('transitionable_id', $service->id)
        ->where('from_state', ChangesRequestedState::$name)
        ->where('to_state', PendingReviewState::$name)
        ->exists()
    )->toBeTrue();
})->group('valid-transitions');

it('vendor can archive published service', function (): void {
    ['user' => $user, 'vendor' => $vendor, 'service' => $service] = makeVendorUserAndService('published');

    $this->actingAs($user);

    $service->status->transition(new ArchiveServiceTransition($service, $user->id));

    $service->refresh();

    expect($service->status)->toBeInstanceOf(ArchivedState::class);

    expect(\DB::table('state_transitions')
        ->where('transitionable_type', Service::class)
        ->where('transitionable_id', $service->id)
        ->where('from_state', PublishedState::$name)
        ->where('to_state', ArchivedState::$name)
        ->exists()
    )->toBeTrue();
})->group('valid-transitions');

it('admin can archive published service', function (): void {
    ['service' => $service] = makeVendorUserAndService('published');

    $this->actingAs($this->admin);

    $service->status->transition(new ArchiveServiceTransition($service, $this->admin->id));

    $service->refresh();

    expect($service->status)->toBeInstanceOf(ArchivedState::class);
})->group('valid-transitions');

it('vendor can unarchive service back to draft', function (): void {
    ['user' => $user, 'service' => $service] = makeVendorUserAndService('archived');

    $this->actingAs($user);

    $service->status->transition(new UnarchiveServiceTransition($service, $user->id));

    $service->refresh();

    expect($service->status)->toBeInstanceOf(DraftState::class);

    expect(\DB::table('state_transitions')
        ->where('transitionable_type', Service::class)
        ->where('transitionable_id', $service->id)
        ->where('from_state', ArchivedState::$name)
        ->where('to_state', DraftState::$name)
        ->exists()
    )->toBeTrue();
})->group('valid-transitions');

it('pending service can transition back to draft directly', function (): void {
    ['service' => $service] = makeVendorUserAndService('pending_review');

    $service->status->transitionTo(DraftState::class);

    expect($service->fresh()->status)->toBeInstanceOf(DraftState::class);
})->group('valid-transitions');

it('changes_requested service can transition back to draft directly', function (): void {
    ['service' => $service] = makeVendorUserAndService('changes_requested');

    $service->status->transitionTo(DraftState::class);

    expect($service->fresh()->status)->toBeInstanceOf(DraftState::class);
})->group('valid-transitions');

it('published service can transition back to pending_review directly', function (): void {
    ['service' => $service] = makeVendorUserAndService('published');

    $service->status->transitionTo(PendingReviewState::class);

    expect($service->fresh()->status)->toBeInstanceOf(PendingReviewState::class);
})->group('valid-transitions');

it('rejected service can transition to archived directly', function (): void {
    ['service' => $service] = makeVendorUserAndService('rejected');

    $service->status->transitionTo(ArchivedState::class);

    expect($service->fresh()->status)->toBeInstanceOf(ArchivedState::class);
})->group('valid-transitions');

// =========================================================================
// T033: Invalid transitions throw and leave DB unchanged
// =========================================================================

it('cannot transition archived directly to published', function (): void {
    ['service' => $service] = makeVendorUserAndService('archived');

    $before = \DB::table('state_transitions')->count();

    expect(fn () => $service->status->transitionTo(PublishedState::class))
        ->toThrow(CouldNotPerformTransition::class);

    expect($service->fresh()->status)->toBeInstanceOf(ArchivedState::class);
    expect(\DB::table('state_transitions')->count())->toBe($before);
})->group('invalid-transitions');

it('cannot transition draft directly to published', function (): void {
    ['service' => $service] = makeVendorUserAndService('draft');

    expect(fn () => $service->status->transitionTo(PublishedState::class))
        ->toThrow(CouldNotPerformTransition::class);

    expect($service->fresh()->status)->toBeInstanceOf(DraftState::class);
})->group('invalid-transitions');

it('cannot transition published directly to rejected', function (): void {
    ['service' => $service] = makeVendorUserAndService('published');

    expect(fn () => $service->status->transitionTo(RejectedState::class))
        ->toThrow(CouldNotPerformTransition::class);

    expect($service->fresh()->status)->toBeInstanceOf(PublishedState::class);
})->group('invalid-transitions');

it('cannot transition rejected directly to published', function (): void {
    ['service' => $service] = makeVendorUserAndService('rejected');

    expect(fn () => $service->status->transitionTo(PublishedState::class))
        ->toThrow(CouldNotPerformTransition::class);

    expect($service->fresh()->status)->toBeInstanceOf(RejectedState::class);
})->group('invalid-transitions');

it('cannot transition draft directly to archived', function (): void {
    ['service' => $service] = makeVendorUserAndService('draft');

    expect(fn () => $service->status->transitionTo(ArchivedState::class))
        ->toThrow(CouldNotPerformTransition::class);

    expect($service->fresh()->status)->toBeInstanceOf(DraftState::class);
})->group('invalid-transitions');

it('cannot transition changes_requested directly to published', function (): void {
    ['service' => $service] = makeVendorUserAndService('changes_requested');

    expect(fn () => $service->status->transitionTo(PublishedState::class))
        ->toThrow(CouldNotPerformTransition::class);

    expect($service->fresh()->status)->toBeInstanceOf(ChangesRequestedState::class);
})->group('invalid-transitions');

// =========================================================================
// T033: Permission guard — vendor cannot approve own service (403)
// =========================================================================

it('vendor cannot approve own service', function (): void {
    ['user' => $user, 'service' => $service] = makeVendorUserAndService('pending_review');

    $this->actingAs($user);

    expect(fn () => (new ApproveServiceTransition($service, $user->id))->handle())
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect($service->fresh()->status)->toBeInstanceOf(PendingReviewState::class);
})->group('permission-guards');

it('vendor cannot reject own service', function (): void {
    ['user' => $user, 'service' => $service] = makeVendorUserAndService('pending_review');

    $this->actingAs($user);

    expect(fn () => (new RejectServiceTransition($service, $user->id))->handle())
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect($service->fresh()->status)->toBeInstanceOf(PendingReviewState::class);
})->group('permission-guards');

it('vendor cannot request changes on own service', function (): void {
    ['user' => $user, 'service' => $service] = makeVendorUserAndService('pending_review');

    $this->actingAs($user);

    expect(fn () => (new RequestServiceChangesTransition($service, $user->id))->handle())
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect($service->fresh()->status)->toBeInstanceOf(PendingReviewState::class);
})->group('permission-guards');

it('non-owner vendor cannot archive another vendors service', function (): void {
    ['service' => $service] = makeVendorUserAndService('published');

    $otherVendorUser = User::factory()->asVendor()->create();

    $this->actingAs($otherVendorUser);

    expect(fn () => (new ArchiveServiceTransition($service, $otherVendorUser->id))->handle())
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect($service->fresh()->status)->toBeInstanceOf(PublishedState::class);
})->group('permission-guards');
