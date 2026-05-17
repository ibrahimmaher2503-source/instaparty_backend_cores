<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\Admin\EscalateChatFlagController;
use App\Modules\Communication\Http\Controllers\Admin\FreezeChatController;
use App\Modules\Communication\Http\Controllers\Admin\MarkOffPlatformContactController;
use App\Modules\Communication\Http\Controllers\Admin\ResolveChatFlagController;
use App\Modules\Communication\Http\Controllers\Admin\UnfreezeChatController;
use Illuminate\Support\Facades\Route;

// Admin chat moderation endpoints (Phase 8.2 — spec 036)
Route::post('chat-threads/{thread}/freeze', FreezeChatController::class)
    ->middleware('can:chat_moderation.freeze')
    ->name('admin.chat-threads.freeze');

Route::post('chat-threads/{thread}/unfreeze', UnfreezeChatController::class)
    ->middleware('can:chat_moderation.unfreeze')
    ->name('admin.chat-threads.unfreeze');

Route::post('chat-moderation-flags/{flag}/resolve', ResolveChatFlagController::class)
    ->middleware('can:chat_moderation.resolve_flag')
    ->name('admin.chat-moderation-flags.resolve');

Route::post('chat-message-logs/{log}/mark-off-platform', MarkOffPlatformContactController::class)
    ->middleware('can:chat_moderation.mark_off_platform')
    ->name('admin.chat-message-logs.mark-off-platform');

Route::post('chat-moderation-flags/{flag}/escalate', EscalateChatFlagController::class)
    ->middleware('can:chat_moderation.escalate')
    ->name('admin.chat-moderation-flags.escalate');
