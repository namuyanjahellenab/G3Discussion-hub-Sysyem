<?php

use Illuminate\Console\Scheduling\Schedule;
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
    ->withSchedule(function (Schedule $schedule): void {
        // Recomputes Active/Inactive/Blacklisted status and runs the
        // inactivity warning/auto-blacklist pipeline. Needs `php artisan
        // schedule:run` invoked every minute by the OS (cron/Task
        // Scheduler) - or `php artisan schedule:work` while developing.
        $schedule->command('students:process-inactivity')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway terminates TLS at its edge and forwards plain HTTP to this
        // container, only marking the original scheme via X-Forwarded-Proto.
        // Railway's edge IP isn't fixed/whitelistable, so all proxies are
        // trusted here (safe - traffic only ever reaches this container
        // through Railway's edge in the first place). Without this,
        // Laravel never sees the request as HTTPS, so url()/route() and
        // form actions all generate http:// links even on a page loaded
        // over https - hence the browser's "not secure" warning on submit.
        $middleware->trustProxies(at: '*');

        // Lets same-origin browser requests (e.g. the topic page's polling
        // fetch()) authenticate against auth:sanctum API routes using the
        // session cookie, same as config/sanctum.php's already-configured
        // 'stateful' domain list expects - without this, that config does
        // nothing and only Bearer tokens (the desktop client) are accepted.
        // Purely additive: token requests are unaffected either way.
        $middleware->statefulApi();

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'blacklist' => \App\Http\Middleware\BlockBlacklistedActions::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
