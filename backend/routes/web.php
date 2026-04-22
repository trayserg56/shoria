<?php

use App\Http\Controllers\SeoController;
use App\Http\Controllers\PublicStorageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*');
