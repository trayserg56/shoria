<?php

use App\Http\Middleware\LogSlowRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Спринт 20: лог медленных запросов API (включается SLOW_REQUEST_LOG_MS > 0).
        $middleware->appendToGroup('api', LogSlowRequests::class);

        // За Nginx в Docker корректно читаются X-Forwarded-* (схема/хост), иначе куки сессии могут не совпасть с URL в браузере.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
