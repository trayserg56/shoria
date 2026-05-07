<?php

use App\Http\Controllers\OAuthController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SpaShellController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SpaShellController::class, 'showRoot']);

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*');

Route::permanentRedirect('/pages/loyalty-program', '/loyalty-program');

Route::middleware('web')->group(function (): void {
    Route::get('/oauth/vk/redirect', [OAuthController::class, 'redirectToVk'])->name('oauth.vk.redirect');
    Route::get('/oauth/vk/callback', [OAuthController::class, 'handleVkCallback'])->name('oauth.vk.callback');
});

Route::get('/{path}', [SpaShellController::class, 'showPath'])->where('path', '^(?!api|admin|storage|livewire|sanctum|oauth).*$');
