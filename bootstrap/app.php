<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware aliases
        $middleware->alias([
            'api.permission' => \App\Http\Middleware\CheckApiClientPermission::class,
            'log.api' => \App\Http\Middleware\LogApiRequest::class,
        ]);

        // Ensure Sanctum middleware is available
        $middleware->use([
            // Global middleware if needed
        ]);

        // API middleware group
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'log.api', // Apply logging globally to API routes
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
