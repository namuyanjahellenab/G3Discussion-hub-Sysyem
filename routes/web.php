<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
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
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
// Define the missing 'register.role' route
Route::get('/register/role', [App\Http\Controllers\Auth\RegisteredUserController::class, 'showRoleSelection'])
    ->name('register.role');
 Route::get('/register/role', [RegisteredUserController::class, 'showRoleSelection'])->name('register.role');   
require __DIR__.'/auth.php';

use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizEngineController;

Route::middleware('auth')->group(function () {
    Route::get('/quiz/schedule', [QuizController::class, 'create'])->name('quiz.schedule');
    Route::get('/quiz/{id}/results', function ($id) {
        return view('quizzes.results', ['quizID' => $id]);
    })->name('quiz.results');
    Route::post('/quiz/schedule-submit', [QuizController::class, 'scheduleAssessment']);
    Route::get('/quiz/{id}/take', function ($id) {
    return view('quizzes.quiz-take');
})->name('quiz.take');
Route::post('/web/quiz/join',        [QuizEngineController::class, 'join'])->middleware('auth');
Route::post('/web/quiz/submit',      [QuizEngineController::class, 'submit'])->middleware('auth');
Route::post('/web/quiz/auto-submit', [QuizEngineController::class, 'autoSubmit'])->middleware('auth');
Route::get('/web/quiz/{id}/results', [QuizEngineController::class, 'results'])->middleware('auth');
Route::get('/quiz/active-now', [QuizEngineController::class, 'activeNow'])->middleware('auth');

});


Route::get('/quiz-test', function() {
    return view('quizzes.quiz-test');
})->middleware('auth');

// ->middleware('auth')

