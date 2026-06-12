<?php

use App\Http\Controllers\Api\AddressSuggestController;
use App\Http\Controllers\Api\OnecExchangeController;
use App\Http\Controllers\Api\OnecRestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\YandexFeedController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GiftCertificateAccountController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\NewsletterSubscriptionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductReviewController;
use App\Http\Controllers\Api\ServicePageController;
use App\Http\Controllers\Api\SiteSettingController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\UserAddressController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->middleware('throttle:180,1');

// 1С: CommerceML 2 обмен + REST API (защищены HTTP Basic Auth)
Route::any('/1c/exchange', [OnecExchangeController::class, 'handle'])
    ->middleware(['App\Http\Middleware\OnecBasicAuth', 'throttle:1c-exchange'])
    ->withoutMiddleware(['throttle:api']);

Route::middleware(['App\Http\Middleware\OnecBasicAuth', 'throttle:1c-exchange'])
    ->withoutMiddleware(['throttle:api'])
    ->prefix('1c')
    ->group(function (): void {
        Route::get('/products', [OnecRestController::class, 'products']);
        Route::get('/stock', [OnecRestController::class, 'stock']);
        Route::get('/orders', [OnecRestController::class, 'orders']);
        Route::patch('/orders/{orderNumber}', [OnecRestController::class, 'updateOrder']);
    });

Route::middleware('throttle:public-api')->group(function (): void {
    Route::get('/home', HomeController::class);
    Route::get('/stats', StatsController::class);
    Route::get('/navigation', NavigationController::class);
    Route::get('/site-settings', SiteSettingController::class);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/search/suggest', [ProductController::class, 'suggest']);
    Route::get('/recommendations/personal', [ProductController::class, 'personalRecommendations']);
    Route::get('/recommendations/cart', [ProductController::class, 'cartRecommendations']);
    Route::get('/products/{slug}/recommendations', [ProductController::class, 'recommendations']);
    Route::get('/products/{slug}/similar', [ProductController::class, 'similar']);
    Route::get('/products/{slug}/reviews', [ProductReviewController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::get('/news/{slug}', [NewsController::class, 'show']);
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/pages', [ServicePageController::class, 'index']);
    Route::get('/pages/{slug}', [ServicePageController::class, 'show']);
    Route::get('/loyalty/info', [LoyaltyController::class, 'info']);
    Route::get('/cart', [CartController::class, 'show']);
    Route::get('/checkout/options', [CheckoutController::class, 'options']);
    Route::get('/delivery/cdek/pickup-points', [DeliveryController::class, 'cdekPickupPoints']);
    Route::get('/delivery/estimate', [DeliveryController::class, 'estimate']);
    Route::get('/address/suggest', [AddressSuggestController::class, 'suggest']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);
    Route::get('/orders/{orderNumber}/payment-url', [OrderController::class, 'paymentUrl']);
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
    ->middleware('throttle:checkout-preview');
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:checkout-write');

Route::post('/payments/webhooks/{providerCode}', [PaymentWebhookController::class, 'store'])
    ->middleware('throttle:webhooks');

Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:auth-register');
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth-login');
Route::post('/auth/oauth/exchange', [AuthController::class, 'exchangeOAuthCode'])
    ->middleware('throttle:auth-oauth-exchange');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:auth-recovery');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:auth-recovery');
Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me/gift-certificates', [GiftCertificateAccountController::class, 'index']);
    Route::get('/reviews/pending', [ProductReviewController::class, 'pending']);
    Route::post('/reviews/quick', [ProductReviewController::class, 'quickRating']);
    Route::post('/checkout/gift-certificate', [CheckoutController::class, 'purchaseGiftCertificate'])
        ->middleware('throttle:checkout-write');
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::get('/reviews/me', [ProductReviewController::class, 'my']);
    Route::post('/products/{slug}/reviews', [ProductReviewController::class, 'upsert']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/feed/yandex/refresh', [YandexFeedController::class, 'refresh']);
    Route::post('/auth/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1');
    Route::get('/loyalty/me', [LoyaltyController::class, 'me']);
    Route::post('/checkout/one-click', [CheckoutController::class, 'oneClick'])
        ->middleware('throttle:checkout-one-click');
    Route::get('/checkout/one-click/suggestions', [CheckoutController::class, 'oneClickSuggestions']);
    Route::get('/me/addresses', [UserAddressController::class, 'index']);
    Route::post('/me/addresses', [UserAddressController::class, 'store']);
    Route::patch('/me/addresses/{address}', [UserAddressController::class, 'update']);
    Route::delete('/me/addresses/{address}', [UserAddressController::class, 'destroy']);
});
