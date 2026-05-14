<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->vendor = VendorProfile::factory()->create();
    $this->plan = SubscriptionPlan::factory()->create();
    $this->sub = VendorSubscription::factory()->create([
        'vendor_profile_id' => $this->vendor->id,
        'subscription_plan_id' => $this->plan->id,
    ]);
});

it('lists subscription invoices with correct columns', function () {
    $start = now()->subDays(15);
    $invoices = SubscriptionInvoice::factory()
        ->count(3)
        ->sequence(
            ['vendor_subscription_id' => $this->sub->id, 'period_start' => $start, 'period_end' => $start->copy()->addMonth()],
            ['vendor_subscription_id' => $this->sub->id, 'period_start' => $start->copy()->addMonth(), 'period_end' => $start->copy()->addMonths(2)],
            ['vendor_subscription_id' => $this->sub->id, 'period_start' => $start->copy()->addMonths(2), 'period_end' => $start->copy()->addMonths(3)],
        )
        ->create();

    $this->actingAs($this->admin)
        ->get('/admin/subscription-invoices')
        ->assertSuccessful();
})->group('subscriptions');

it('filters invoices by status', function () {
    $start = now()->subDays(15);
    SubscriptionInvoice::factory()
        ->create(['vendor_subscription_id' => $this->sub->id, 'status' => InvoiceStatus::Pending, 'period_start' => $start, 'period_end' => $start->copy()->addMonth()]);
    SubscriptionInvoice::factory()
        ->create(['vendor_subscription_id' => $this->sub->id, 'status' => InvoiceStatus::Paid, 'period_start' => $start->copy()->addMonth(), 'period_end' => $start->copy()->addMonths(2)]);

    $this->actingAs($this->admin)
        ->get('/admin/subscription-invoices?tableFilters[status][value]=paid')
        ->assertSuccessful();
})->group('subscriptions');

it('shows invoice details in view page', function () {
    $invoice = SubscriptionInvoice::factory()
        ->create([
            'vendor_subscription_id' => $this->sub->id,
            'amount_minor' => 100000,
            'period_start' => Carbon::now(),
            'period_end' => Carbon::now()->addMonth(),
        ]);

    $this->actingAs($this->admin)
        ->get("/admin/subscription-invoices/{$invoice->id}")
        ->assertSuccessful();
})->group('subscriptions');

it('prevents invoice creation from admin panel', function () {
    $this->actingAs($this->admin)
        ->get('/admin/subscription-invoices/create')
        ->assertNotFound();
})->group('subscriptions');

it('prevents invoice editing from admin panel', function () {
    $invoice = SubscriptionInvoice::factory()
        ->create(['vendor_subscription_id' => $this->sub->id]);

    $this->actingAs($this->admin)
        ->get("/admin/subscription-invoices/{$invoice->id}/edit")
        ->assertNotFound();
})->group('subscriptions');

it('displays embedded subscription payments for invoice', function () {
    $invoice = SubscriptionInvoice::factory()
        ->create(['vendor_subscription_id' => $this->sub->id]);

    $this->actingAs($this->admin)
        ->get("/admin/subscription-invoices/{$invoice->id}")
        ->assertSuccessful();
})->group('subscriptions');
