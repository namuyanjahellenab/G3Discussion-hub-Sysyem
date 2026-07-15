<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flag escalation threshold
    |--------------------------------------------------------------------------
    |
    | Number of distinct members who must flag a post/reply before it's
    | surfaced in the admin moderation queue (Post.IsFlagged / Reply.IsFlagged
    | flip to true). Below this count the flag is recorded but not escalated.
    |
    */
    'flag_escalation_threshold' => env('MODERATION_FLAG_THRESHOLD', 2),

    /*
    |--------------------------------------------------------------------------
    | Warning threshold
    |--------------------------------------------------------------------------
    |
    | Number of active (unexpired) warnings before a user is auto-blacklisted.
    | Mirrors AdminWarningController::WARNING_THRESHOLD.
    |
    */
    'warning_threshold' => env('MODERATION_WARNING_THRESHOLD', 2),

];
