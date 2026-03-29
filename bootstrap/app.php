<?php

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
        // So $request->root() is https://your-domain behind nginx / load balancers (X-Forwarded-*).
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'vendor' => \App\Http\Middleware\EnsureUserisVendor::class,
            'customer' => \App\Http\Middleware\EnsureUserisCustomer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
