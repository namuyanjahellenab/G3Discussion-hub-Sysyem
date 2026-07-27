<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::user()?->Role !== 'Administrator') {
            // A lecturer demoted while still sitting on an admin page hits
            // this on their very next request into /admin/* (Role has
            // already flipped back to Lecturer) - send them to /dashboard
            // instead of a bare 403, which resolves straight to their
            // lecturer dashboard via DashboardController::index()'s own
            // Role check. Left as a 403 for JSON/AJAX callers so nothing
            // depending on that status code silently starts following a
            // redirect instead.
            if ($request->expectsJson()) {
                abort(403, 'Unauthorized.');
            }

            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
