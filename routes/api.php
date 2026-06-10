<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BulkNotificationController;
use App\Http\Controllers\Api\ProviderWebhookController;
use App\Http\Controllers\Api\RecipientHistoryController;
use Illuminate\Support\Facades\Route;

Route::post('/notifications/bulk', [BulkNotificationController::class, 'store']);
Route::get('/notifications/by-recipient/{recipient_id}', [RecipientHistoryController::class, 'show']);
Route::post('/notifications/webhooks/{provider}', ProviderWebhookController::class)
    ->where('provider', '[A-Za-z0-9_-]{1,64}');
