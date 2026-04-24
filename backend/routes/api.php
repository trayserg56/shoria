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

Route::middleware('throttle:public-api')->group(function (): void {
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
    Route::get('/cart', [CartController::class, 'show']);
    Route::get('/checkout/options', [CheckoutController::class, 'options']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);
});

Route::post('/events', [EventController::class, 'store'])
    ->middleware('throttle:events');
Route::post('/newsletter/subscribe', [NewsletterSubscriptionController::class, 'store'])
    ->middleware('throttle:newsletter');

Route::post('/cart/items', [CartController::class, 'addItem'])
    ->middleware('throttle:cart-write');
Route::patch('/cart/items/{itemId}', [CartController::class, 'updateItem'])
    ->middleware('throttle:cart-write');
Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem'])
    ->middleware('throttle:cart-write');

Route::post('/checkout/preview', [CheckoutController::class, 'preview'])
    ->middleware('throttle:checkout-write');
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:checkout-write');

Route::post('/payments/webhooks/{providerCode}', [PaymentWebhookController::class, 'store'])
    ->middleware('throttle:webhooks');

Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:auth-register');
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth-login');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:auth-recovery');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:auth-recovery');
Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1');
});
