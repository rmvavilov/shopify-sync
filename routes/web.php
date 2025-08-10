<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\WebhookController;

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/api/me', [AuthController::class, 'me'])->name('me');

Route::middleware('auth:web')->group(function () {
    Route::get('/api/shopify/products', [ShopifyController::class, 'products']);

    Route::get('/api/some-protected-endpoint', fn () => response()->json(['ok' => true]));
});

Route::post('/api/webhooks/shopify/products/create', [WebhookController::class, 'productCreate']);
Route::post('/api/webhooks/shopify/products/update', [WebhookController::class, 'productUpdate']);
Route::post('/api/webhooks/shopify/products/delete', [WebhookController::class, 'productDelete']);

Route::view('/{any}', 'app')->where('any', '.*');
