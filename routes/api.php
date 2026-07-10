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
Route::middleware('auth:sanctum')->get('/quiz/active-now', [QuizEngineController::class, 'activeNow']);
Route::middleware('auth:sanctum')->post('/quiz/join', [QuizEngineController::class, 'join']);
Route::middleware('auth:sanctum')->post('/quiz/submit', [QuizEngineController::class, 'submit']);
Route::middleware('auth:sanctum')->post('/quiz/auto-submit', [QuizEngineController::class, 'autoSubmit']);
Route::middleware('auth:sanctum')->get('/quiz/{id}/results', [QuizEngineController::class, 'results']);
//Route::get('/user', function (Request $request) {
  //  return $request->user();
//})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/groups', [App\Http\Controllers\Api\GroupApiController::class, 'index']);
    Route::post('/groups/{group}/join', [App\Http\Controllers\Api\GroupApiController::class, 'join']);
    Route::post('/groups/{group}/mark-viewed', [App\Http\Controllers\Api\GroupApiController::class, 'markViewed']);

    // Group forum: topic listing/creation/viewing + replying.
    // These mirror DiscussionHubPageController's web routes exactly
    // (groupTopics/createTopic/storeTopic/showTopic/storeReply/acceptAnswer)
    // so the JavaFX client behaves identically to the web app.
    Route::get('/groups/{group}/topics', [App\Http\Controllers\Api\TopicApiController::class, 'index']);
    Route::post('/topics', [App\Http\Controllers\Api\TopicApiController::class, 'store']);
    Route::get('/topics/{topic}', [App\Http\Controllers\Api\TopicApiController::class, 'show']);
    Route::post('/posts/{post}/reply', [App\Http\Controllers\Api\ReplyApiController::class, 'store']);
    Route::post('/replies/{reply}/accept', [App\Http\Controllers\Api\ReplyApiController::class, 'accept']);
    Route::get('/sync/pull', [App\Http\Controllers\Api\SyncController::class, 'pull']);
    Route::post('/sync/push', [App\Http\Controllers\Api\SyncController::class, 'push']);
    Route::put('/settings', [App\Http\Controllers\Api\SettingsController::class, 'update']);
    Route::post('/logout', [App\Http\Controllers\Api\SettingsController::class, 'logout']);
});

// Route::middleware('auth')->group(function () {
//     Route::post('/quiz/schedule',    [QuizController::class,       'scheduleAssessment']);
//     // Route::post('/quiz/join',        [QuizEngineController::class, 'join']);
//     // Route::post('/quiz/submit',      [QuizEngineController::class, 'submit']);
//     // Route::post('/quiz/auto-submit', [QuizEngineController::class, 'autoSubmit']);
//     // Route::get('/quiz/{id}/results', [QuizEngineController::class, 'results']);
// });
