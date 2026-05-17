<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('pending vendor approvals heading is non-empty Arabic string in ar locale', function (): void {
    app()->setLocale('ar');

    $translated = __('identity::widgets.pending_vendor_approvals_heading');

    expect($translated)->not->toBe('identity::widgets.pending_vendor_approvals_heading');
    expect($translated)->not->toBeEmpty();
})->group('widgets', 'admin', 'locale');

it('pending vendor approvals heading is English string in en locale', function (): void {
    app()->setLocale('en');

    $translated = __('identity::widgets.pending_vendor_approvals_heading');

    expect($translated)->toBe('Pending Vendor Approvals');
})->group('widgets', 'admin', 'locale');

it('all 9 widget heading keys return Arabic strings in ar locale', function (): void {
    app()->setLocale('ar');

    $keys = [
        'identity::widgets.pending_vendor_approvals_heading',
        'catalog::widgets.pending_service_moderation_rental_heading',
        'catalog::widgets.pending_service_moderation_sale_heading',
        'catalog::widgets.pending_service_moderation_digital_heading',
        'booking::widgets.late_vendor_responses_heading',
        'booking::widgets.bookings_waiting_customer_approval_heading',
        'payments::widgets.failed_payments_heading',
        'communication::widgets.failed_notification_dispatches_heading',
        'communication::widgets.critical_admin_inbox_heading',
        'settlement::widgets.pending_withdrawals_heading',
        'catalog::widgets.excel_imports_with_errors_heading',
    ];

    foreach ($keys as $key) {
        $translated = __($key);
        expect($translated)->not->toBe($key, "Translation key '{$key}' was not found in AR locale");
        expect($translated)->not->toBeEmpty();
    }
})->group('widgets', 'admin', 'locale');
