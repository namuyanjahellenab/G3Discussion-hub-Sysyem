<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class TriggerInactivitySweep
{
    /**
     * This app has no OS-level cron / Windows Task Scheduler calling
     * `php artisan schedule:run` every minute, so the students:process-
     * inactivity command registered in bootstrap/app.php never actually
     * fires on its own - the periods configured on the Blacklist Settings
     * screen would silently do nothing. Piggybacking the sweep on admin
     * page loads (throttled to once a minute via a cache lock, so it's a
     * cheap no-op on every request but the first each minute) makes the
     * configured periods actually take effect without any server setup.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Cache::add('inactivity_sweep_lock', true, 60)) {
            Artisan::call('students:process-inactivity');
        }

        return $next($request);
    }
}
