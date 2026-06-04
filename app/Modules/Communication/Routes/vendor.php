<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\NotificationPreferenceController;
use App\Modules\Communication\Http\Controllers\VendorNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:vendor')->group(function () {
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'indexVendor']);
    Route::put('/notification-preferences/{channel}/{event_category}', [NotificationPreferenceController::class, 'updateVendor']);

    // In-app notifications inbox (vendor-portal 17.1–17.5 / G11).
    Route::get('/notifications', [VendorNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [VendorNotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{publicId}/mark-read', [VendorNotificationController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [VendorNotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{publicId}', [VendorNotificationController::class, 'destroy']);
});
