<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnecBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = config('services.onec.username', '1c');
        $pass = config('services.onec.password', '');

        if (! $pass) {
            abort(503, '1C exchange not configured');
        }

        if ($request->getUser() !== $user || $request->getPassword() !== $pass) {
            return response('failure' . PHP_EOL . 'Неверные учётные данные', 401, [
                'WWW-Authenticate' => 'Basic realm="1C Exchange"',
            ]);
        }

        return $next($request);
    }
}
