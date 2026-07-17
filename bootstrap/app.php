<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lets same-origin browser requests (e.g. the topic page's polling
        // fetch()) authenticate against auth:sanctum API routes using the
        // session cookie, same as config/sanctum.php's already-configured
        // 'stateful' domain list expects - without this, that config does
        // nothing and only Bearer tokens (the desktop client) are accepted.
        // Purely additive: token requests are unaffected either way.
        $middleware->statefulApi();

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
