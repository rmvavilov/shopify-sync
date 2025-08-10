<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\ProductDetailsController;
use App\Http\Controllers\ProductSyncController;
use App\Http\Controllers\ProductLiveMutationsController;

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/api/me', [AuthController::class, 'me'])->name('me');

Route::middleware('auth:web')->group(function () {
    Route::get('/api/shopify/products', [ShopifyController::class, 'products']);
    Route::get('/api/shopify/products/proxy', [ShopifyController::class, 'productsProxy']);
    Route::get('/api/shopify/products/local', [ShopifyController::class, 'productsLocal']);
    Route::get('/api/shopify/products/proxy/{id64}', [ProductDetailsController::class, 'showProxy']);
    Route::get('/api/shopify/products/local/{id64}', [ProductDetailsController::class, 'showLocal']);

    Route::post('/api/shopify/products/sync/{id64}', [ProductSyncController::class, 'syncOne']);

    Route::post('/api/shopify/products/live/{id64}/update', [ProductLiveMutationsController::class, 'update']);
    Route::delete('/api/shopify/products/live/{id64}', [ProductLiveMutationsController::class, 'destroy']);

    Route::get('/api/some-protected-endpoint', fn() => response()->json(['ok' => true]));
    Route::get('/api/some-protected-endpoint', function () {
        return response()->json(['ok' => true]);
    });
});

Route::post('/api/webhooks/shopify/products/create', [WebhookController::class, 'productCreate']);
Route::post('/api/webhooks/shopify/products/update', [WebhookController::class, 'productUpdate']);
Route::post('/api/webhooks/shopify/products/delete', [WebhookController::class, 'productDelete']);

Route::view('/{any}', 'app')->where('any', '.*');
