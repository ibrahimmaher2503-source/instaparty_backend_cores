<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

/**
 * Vendor-portal 17.1–17.5 — the in-app inbox is user-scoped, not
 * role-specific: the customer controller's queries already key on
 * auth()->id() + channel=in_app. This subclass exists so vendor routes
 * don't reference a Customer-named class (G11, unblocked by the read_at
 * column shipped with the customer F17 work).
 *
 * @group Vendor - Notifications
 */
class VendorNotificationController extends CustomerNotificationController {}
