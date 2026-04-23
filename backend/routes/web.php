<?php

use App\Http\Controllers\SeoController;
use App\Http\Controllers\PublicStorageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $spaIndex = public_path('spa-index.html');

    if (is_file($spaIndex)) {
        return response()->file($spaIndex);
    }

    return view('welcome');
});

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*');

Route::get('/{path}', function (string $path) {
    $publicAssetPath = public_path($path);
    if (is_file($publicAssetPath)) {
        return response()->file($publicAssetPath);
    }

    $spaIndex = public_path('spa-index.html');
    if (is_file($spaIndex)) {
        return response()->file($spaIndex);
    }

    abort(404);
})->where('path', '^(?!api|admin|storage|livewire|sanctum).*$');
