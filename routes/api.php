<?php

use App\Models\Message;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\QuizEngineController;
use App\Http\Controllers\QuizController;


Route::post('/login', [AuthController::class, 'apiLogin']);
Route::post('/register', [AuthController::class, 'apiRegister']);
Route::post('/forgot-password', [AuthController::class, 'apiForgotPassword']);

// Deliberately outside auth:sanctum and every other middleware - the
// desktop's NetworkUtil.isNetworkAvailable() probes this to answer "is the
// server reachable at all", so it must not depend on auth state and must
// resolve as fast as physically possible. Probing an authenticated route
// (e.g. GET /api/login, which 405s) was taking 1.5-4s under the dev server
// because it still boots the full Sanctum/session stack before rejecting
// the method - long enough to trip the 2s timeout and show a false OFFLINE.
Route::get('/ping', fn () => response()->json(['status' => 'ok']));
Route::middleware('auth:sanctum')->get('/quiz/active-now', [QuizEngineController::class, 'activeNow']);
Route::middleware('auth:sanctum')->post('/quiz/join', [QuizEngineController::class, 'join']);
Route::middleware('auth:sanctum')->post('/quiz/submit', [QuizEngineController::class, 'submit']);
Route::middleware('auth:sanctum')->post('/quiz/auto-submit', [QuizEngineController::class, 'autoSubmit']);
Route::middleware('auth:sanctum')->get('/quiz/{id}/results', [QuizEngineController::class, 'results']);
Route::middleware('auth:sanctum')->get('/quiz/{id}/my-review', [QuizEngineController::class, 'myReviewApi']);
//Route::get('/user', function (Request $request) {
  //  return $request->user();
//})->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->post('/topics', function (Request $request) {
    $request->validate([
        'title' => 'required|string|max:255',
    ]);

    $topic = Topic::create([
        'user_id' => $request->user()->id,
        'title' => $request->title,
    ]);

    return response()->json($topic, 201);
});

// 2. Fetch all messages belonging to ONE specific topic (Solves Requirement 2)
Route::middleware('auth:sanctum')->get('/topics/{id}/messages', function ($id) {
    $topic = Topic::with('messages.user')->findOrFail($id);

    return response()->json([
        'topic' => $topic->title,
        'messages' => $topic->messages
    ]);
});

// 3. Reply directly inside a topic
Route::middleware(['auth:sanctum', 'throttle:forum-posts'])->post('/topics/{id}/messages', function (Request $request, $id) {
    $request->validate([
        'body' => 'required|string',
    ]);

    $message = Message::create([
        'topic_id' => $id,
        'user_id' => $request->user()->id,
        'body' => $request->body,
    ]);

    return response()->json($message, 201);
});

Route::middleware('auth:sanctum')->post('/topics', function (Request $request) {
    $request->validate([
        'title' => 'required|string|max:255',
        'exclude_user_ids' => 'nullable|array', // Array of user IDs to exclude [2, 5, 9]
        'exclude_user_ids.*' => 'exists:users,id'
    ]);

    // Create the topic
    $topic = Topic::create([
        'user_id' => $request->user()->id,
        'title' => $request->title,
    ]);

    // If there are excluded users, save them to our new table
    if ($request->has('exclude_user_ids')) {
        foreach ($request->exclude_user_ids as $userId) {
            DB::table('topic_exclusions')->insert([
                'topic_id' => $topic->id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    return response()->json(['message' => 'Topic created successfully', 'topic' => $topic], 201);
});

// 2. Fetch all topics, BUT hide the ones where the current logged-in user is excluded
Route::middleware('auth:sanctum')->get('/topics', function (Request $request) {
    $currentUserId = $request->user()->id;

    $visibleTopics = Topic::whereNotExists(function ($query) use ($currentUserId) {
        $query->select(DB::raw(1))
              ->from('topic_exclusions')
              ->whereColumn('topic_exclusions.topic_id', 'topics.id')
              ->where('topic_exclusions.user_id', $currentUserId);
    })->get();

    return response()->json($visibleTopics);
});
Route::middleware('auth:sanctum')->group(function () {
    // The default /broadcasting/auth route (registered via the `channels`
    // key in bootstrap/app.php) only accepts the `web` session guard, which
    // covers the browser client. The desktop client authenticates with a
    // Sanctum bearer token instead, so it needs its own auth endpoint under
    // the same channel-authorization callbacks in routes/channels.php.
    Route::post('/broadcasting/auth', fn (Request $request) => \Illuminate\Support\Facades\Broadcast::auth($request));

    Route::get('/groups', [App\Http\Controllers\Api\GroupApiController::class, 'index']);
    Route::get('/groups/browse', [App\Http\Controllers\Api\GroupApiController::class, 'browse']);
    Route::get('/groups/for-selection', [App\Http\Controllers\Api\GroupApiController::class, 'forSelection']);
    Route::post('/groups/{group}/join', [App\Http\Controllers\Api\GroupApiController::class, 'join']);
    Route::delete('/groups/{group}/leave', [App\Http\Controllers\Api\GroupApiController::class, 'leave']);
    Route::post('/groups/{group}/mark-viewed', [App\Http\Controllers\Api\GroupApiController::class, 'markViewed']);
    Route::get('/sync/pull', [App\Http\Controllers\Api\SyncController::class, 'pull']);
    Route::post('/sync/push', [App\Http\Controllers\Api\SyncController::class, 'push']);
    Route::put('/settings', [App\Http\Controllers\Api\SettingsController::class, 'update']);
    Route::post('/logout', [App\Http\Controllers\Api\SettingsController::class, 'logout']);
    Route::post('/logout-all-devices', [App\Http\Controllers\Api\SettingsController::class, 'logoutAllDevices']);

    // Desktop-client equivalents of the web sidebar's My Questions, Marks,
    // Recommend, and Quizzes pages, plus the Dashboard itself.
    Route::get('/dashboard', [App\Http\Controllers\Api\StudentDataApiController::class, 'dashboard']);
    Route::get('/notifications/poll', [App\Http\Controllers\Api\StudentDataApiController::class, 'pollNotifications']);
    Route::post('/notifications/{notification}/read', [App\Http\Controllers\Api\StudentDataApiController::class, 'markNotificationRead']);
    Route::post('/notifications/read-all', [App\Http\Controllers\Api\StudentDataApiController::class, 'markAllNotificationsRead']);
    Route::get('/forum', [App\Http\Controllers\Api\StudentDataApiController::class, 'forum']);
    Route::get('/groups/{group}/topics', [App\Http\Controllers\Api\GroupTopicsApiController::class, 'index']);
    Route::post('/groups/{group}/topics', [App\Http\Controllers\Api\GroupTopicsApiController::class, 'store']);

    Route::get('/topics/{topic}', [App\Http\Controllers\Api\TopicApiController::class, 'show']);
    Route::get('/topics/{topic}/export', [App\Http\Controllers\Api\TopicApiController::class, 'export']);
    Route::get('/topics/{topic}/share-links', [App\Http\Controllers\Api\TopicApiController::class, 'shareLinks']);
    Route::post('/posts/{post}/replies', [App\Http\Controllers\Api\TopicApiController::class, 'storeReply']);
    Route::post('/replies/{reply}/flag', [App\Http\Controllers\Api\TopicApiController::class, 'flagReply']);
    Route::delete('/replies/{reply}/flag', [App\Http\Controllers\Api\TopicApiController::class, 'unflagReply']);
    Route::delete('/replies/{reply}', [App\Http\Controllers\Api\TopicApiController::class, 'deleteReply']);
    Route::post('/replies/{reply}/accept', [App\Http\Controllers\Api\TopicApiController::class, 'acceptAnswer']);
    Route::get('/my-questions', [App\Http\Controllers\Api\StudentDataApiController::class, 'myQuestions']);
    Route::get('/marks', [App\Http\Controllers\Api\StudentDataApiController::class, 'marks']);
    Route::get('/recommend', [App\Http\Controllers\Api\StudentDataApiController::class, 'recommend']);
    Route::get('/quizzes', [App\Http\Controllers\Api\StudentDataApiController::class, 'quizzes']);

    // Desktop-client equivalent of the web's Group Chat (main conversation only).
    Route::get('/groups/{groupId}/chat-messages', [App\Http\Controllers\Api\GroupChatApiController::class, 'index']);
    Route::post('/groups/{groupId}/chat-messages', [App\Http\Controllers\Api\GroupChatApiController::class, 'store']);
    Route::patch('/group-messages/{message}', [App\Http\Controllers\Api\GroupChatApiController::class, 'update']);
    Route::delete('/group-messages/{message}', [App\Http\Controllers\Api\GroupChatApiController::class, 'destroy']);
});

// Route::middleware('auth')->group(function () {
//     Route::post('/quiz/schedule',    [QuizController::class,       'scheduleAssessment']);
//     // Route::post('/quiz/join',        [QuizEngineController::class, 'join']);
//     // Route::post('/quiz/submit',      [QuizEngineController::class, 'submit']);
//     // Route::post('/quiz/auto-submit', [QuizEngineController::class, 'autoSubmit']);
//     // Route::get('/quiz/{id}/results', [QuizEngineController::class, 'results']);
// });
