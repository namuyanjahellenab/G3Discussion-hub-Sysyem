<?php

namespace App\Http\Middleware;

use App\Models\Blacklist;
use Closure;
use Illuminate\Http\Request;

class BlockBlacklistedActions
{
    /**
     * Stops an actively-blacklisted student from creating new content
     * (topics, replies, chat messages) instead of letting the write fail
     * silently or with a generic error. Read-only routes are unaffected.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $activeBlacklist = Blacklist::where('UserID', $user->UserID)->active()->latest('CreatedAt')->first();

            if ($activeBlacklist) {
                $message = "Your account is restricted until {$activeBlacklist->EndDate->format('d M Y')} ({$activeBlacklist->Reason}). You can't post, send, or view messages while restricted.";

                if ($request->expectsJson()) {
                    // 422 rather than 403: some of the existing composer JS only
                    // surfaces the server's message on a 422 response, falling
                    // back to a generic error for other non-2xx statuses.
                    return response()->json([
                        'message' => $message,
                        'errors' => ['blacklist' => [$message]],
                    ], 422);
                }

                return back()->withErrors(['blacklist' => $message])->withInput();
            }
        }

        return $next($request);
    }
}
