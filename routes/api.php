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
Route::middleware('auth:sanctum')->get('/quiz/active-now', [QuizEngineController::class, 'activeNow']);
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

 


// Route::middleware('auth')->group(function () {
//     Route::post('/quiz/schedule',    [QuizController::class,       'scheduleAssessment']);
//     // Route::post('/quiz/join',        [QuizEngineController::class, 'join']);
//     // Route::post('/quiz/submit',      [QuizEngineController::class, 'submit']);
//     // Route::post('/quiz/auto-submit', [QuizEngineController::class, 'autoSubmit']);
//     // Route::get('/quiz/{id}/results', [QuizEngineController::class, 'results']);
// });