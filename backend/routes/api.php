<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\NewsletterSubscriptionController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/home', HomeController::class);
Route::get('/navigation', NavigationController::class);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/search/suggest', [ProductController::class, 'suggest']);
Route::get('/recommendations/personal', [ProductController::class, 'personalRecommendations']);
Route::get('/products/{slug}/recommendations', [ProductController::class, 'recommendations']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/news/{slug}', [NewsController::class, 'show']);
Route::get('/news', [NewsController::class, 'index']);
Route::post('/events', [EventController::class, 'store']);
Route::post('/newsletter/subscribe', [NewsletterSubscriptionController::class, 'store']);
Route::get('/cart', [CartController::class, 'show']);
Route::post('/cart/items', [CartController::class, 'addItem']);
Route::patch('/cart/items/{itemId}', [CartController::class, 'updateItem']);
Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
Route::get('/checkout/options', [CheckoutController::class, 'options']);
Route::post('/checkout/preview', [CheckoutController::class, 'preview']);
Route::post('/checkout', [CheckoutController::class, 'store']);
Route::post('/payments/webhooks/{providerCode}', [PaymentWebhookController::class, 'store']);
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1');
});
