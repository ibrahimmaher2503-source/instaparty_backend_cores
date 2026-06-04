<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\ChatIdentityController;
use App\Modules\Communication\Http\Controllers\CustomerChatThreadController;
use App\Modules\Communication\Http\Controllers\CustomerNotificationController;
use App\Modules\Communication\Http\Controllers\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:customer')->group(function () {
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'indexCustomer']);
    Route::put('/notification-preferences/{channel}/{event_category}', [NotificationPreferenceController::class, 'updateCustomer']);

    // In-app notifications inbox (F17).
    Route::get('/notifications', [CustomerNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [CustomerNotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{publicId}/mark-read', [CustomerNotificationController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [CustomerNotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{publicId}', [CustomerNotificationController::class, 'destroy']);

    // Chat identity for the Firebase custom-token bridge (server-to-server).
    Route::get('/chat/identity', ChatIdentityController::class);

    // Chat threads for a booking (one per vendor).
    Route::get('/bookings/{booking}/chat-threads', CustomerChatThreadController::class);
});
