<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscussionHubPageController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupSelectionController;
use App\Http\Controllers\ProfileController;
use App\Models\Group;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;
Route::get('/', function () {
    return redirect('/login');
});
// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterRole'])->name('register.role');
    Route::post('/register/role', [AuthController::class, 'storeRole'])->name('register.role.store');
    Route::get('/register/details', [AuthController::class, 'showRegisterDetails'])->name('register.details');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::get('/register/role', [RegisteredUserController::class, 'showRoleSelection'])
    ->name('register.role');
    Route::get('/register', [RegisteredUserController::class, 'create'])
    ->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/groups/select', [GroupSelectionController::class, 'index'])
        ->middleware('verified')
        ->name('groups.select');

    Route::post('/groups/join/{group}', [GroupController::class, 'join'])
        ->middleware('verified')
        ->name('groups.join');

    Route::delete('/groups/leave/{group}', [GroupController::class, 'leave'])
        ->middleware('verified')
        ->name('groups.leave');

    Route::get('/groups', [GroupController::class, 'index'])
        ->middleware('verified')
        ->name('groups.index');

    Route::get('/groups/{group}/forum', [GroupController::class, 'forum'])
        ->middleware('verified')
        ->name('groups.forum');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('verified')
        ->name('dashboard');

    Route::get('/forum', [DiscussionHubPageController::class, 'forum'])
        ->middleware('verified')
        ->name('forum.index');

    Route::get('/messages', [DiscussionHubPageController::class, 'messages'])
        ->middleware('verified')
        ->name('messages.index');

    Route::post('/messages', [DiscussionHubPageController::class, 'storeMessage'])
        ->middleware('verified')
        ->name('messages.store');

    Route::get('/messages/{post}/attachment', [AttachmentController::class, 'download'])
        ->middleware('verified')
        ->name('messages.attachment');

    Route::get('/topics/{topic}/export', [DiscussionHubPageController::class, 'exportTopic'])
        ->middleware('verified')
        ->name('topics.export');

    Route::get('/marks', [DiscussionHubPageController::class, 'marks'])
        ->middleware('verified')
        ->name('marks.index');

    Route::get('/quizzes', [DiscussionHubPageController::class, 'quizzes'])
        ->middleware('verified')
        ->name('quizzes.index');

    Route::get('/recommend', [DiscussionHubPageController::class, 'recommend'])
        ->middleware('verified')
        ->name('recommend.index');

    Route::get('/settings', [DiscussionHubPageController::class, 'settings'])
        ->middleware('verified')
        ->name('settings.index');

    Route::post('/settings', [DiscussionHubPageController::class, 'updateSettings'])
        ->middleware('verified')
        ->name('settings.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/discussion-group', function () {
        return view('discussions.group');
    })->name('discussions.group');

    Route::get('/discussion-thread', function () {
        return view('discussions.thread');
    })->name('discussions.thread');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
require __DIR__.'/auth.php';
