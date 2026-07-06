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
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
