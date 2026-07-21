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
use App\Http\Controllers\AdminLecturerStaffController;
use App\Http\Controllers\GroupChatController;
Route::get('/', function () {
    return redirect('/login');
});

// No auth middleware — monitoring tools/load balancers hit this without a
// session. Laravel's built-in /up only confirms the app booted; this also
// reports whether the (separate-process) ML gateway is actually reachable.
Route::get('/health', [\App\Http\Controllers\HealthController::class, 'status'])->name('health');
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

    Route::get('/groups/select', [GroupSelectionController::class, 'index'])
        ->name('groups.select');

    Route::post('/groups/join/{group}', [GroupController::class, 'join'])
        ->name('groups.join');

    Route::post('/groups/select/multiple', [GroupSelectionController::class, 'joinMultiple'])
        ->name('groups.select.multiple');

    Route::delete('/groups/leave/{group}', [GroupController::class, 'leave'])
        ->name('groups.leave');

    Route::get('/groups', [GroupController::class, 'index'])
        ->middleware('verified')
        ->name('groups.index');

    Route::get('/groups/{group}/forum', [GroupController::class, 'forum'])
        ->middleware('verified')
        ->name('groups.forum');

        Route::get('/groups/{group}/topics', [DiscussionHubPageController::class, 'groupTopics'])
    ->middleware('verified')->name('groups.topics');

Route::get('/topics/create/{group}', [DiscussionHubPageController::class, 'createTopic'])
    ->middleware('verified')->name('topics.create');

Route::post('/topics', [DiscussionHubPageController::class, 'storeTopic'])
    ->middleware(['verified', 'blacklist'])->name('topics.store');

Route::get('/topics/my-questions', [DiscussionHubPageController::class, 'myQuestions'])
    ->middleware('verified')->name('topics.my-questions');

Route::get('/topics/{topic}', [DiscussionHubPageController::class, 'showTopic'])
    ->middleware('verified')->name('topics.show');

Route::post('/posts/{post}/reply', [DiscussionHubPageController::class, 'storeReply'])
    ->middleware(['verified', 'blacklist'])->name('posts.reply');

Route::post('/replies/{reply}/accept', [DiscussionHubPageController::class, 'acceptAnswer'])
    ->middleware('verified')->name('replies.accept');

Route::post('/posts/{post}/flag', [DiscussionHubPageController::class, 'flagPost'])
    ->middleware('verified')->name('posts.flag');

Route::post('/replies/{reply}/flag', [DiscussionHubPageController::class, 'flagReply'])
    ->middleware('verified')->name('replies.flag');

Route::delete('/replies/{reply}/flag', [DiscussionHubPageController::class, 'unflagReply'])
    ->middleware('verified')->name('replies.unflag');

Route::delete('/replies/{reply}', [DiscussionHubPageController::class, 'deleteReply'])
    ->middleware('verified')->name('replies.destroy');

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
        ->middleware(['verified', 'blacklist'])
        ->name('messages.store');

    Route::get('/messages/poll', [DiscussionHubPageController::class, 'pollMessages'])
        ->middleware('verified')
        ->name('messages.poll');

    Route::delete('/messages/{post}', [DiscussionHubPageController::class, 'deleteMessage'])
        ->middleware('verified')
        ->name('messages.destroy');

    Route::get('/messages/{post}/attachment', [AttachmentController::class, 'download'])
        ->middleware('verified')
        ->name('messages.attachment');

    Route::get('/topics/{topic}/export', [DiscussionHubPageController::class, 'exportTopic'])
        ->middleware('verified')
        ->name('topics.export');

    Route::get('/marks', [DashboardController::class, 'marks'])
    ->middleware('verified')
    ->name('marks.index');
    
    Route::get('/quizzes', [DiscussionHubPageController::class, 'quizzes'])
        ->middleware('verified')
        ->name('quizzes.index');

    Route::get('/recommend', [DiscussionHubPageController::class, 'recommend'])
        ->middleware('verified')
        ->name('recommend.index');

    Route::post('/recommend/dismiss', [DiscussionHubPageController::class, 'dismissRecommendations'])
        ->middleware('verified')
        ->name('recommend.dismiss');

    Route::get('/settings', [DiscussionHubPageController::class, 'settings'])
        ->middleware('verified')
        ->name('settings.index');

    Route::post('/settings', [DiscussionHubPageController::class, 'updateSettings'])
        ->middleware('verified')
        ->name('settings.update');

    Route::post('/settings/logout-all-devices', [DiscussionHubPageController::class, 'logoutAllDevices'])
        ->middleware('verified')
        ->name('settings.logout-all-devices');

    Route::get('/announcements', [DashboardController::class, 'announcementsIndex'])
        ->middleware('verified')
        ->name('announcements.index');

    Route::get('/announcements/create', [DashboardController::class, 'createAnnouncement'])
        ->middleware('verified')
        ->name('announcements.create');

    Route::post('/announcements', [DashboardController::class, 'storeAnnouncement'])
        ->middleware('verified')
        ->name('announcements.store');
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

use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizEngineController;

Route::middleware('auth')->group(function () {
    Route::get('/quiz/schedule', [QuizController::class, 'create'])->name('quiz.schedule');
    Route::get('/quiz/drafts', [QuizController::class, 'drafts'])->name('quiz.drafts');
    Route::post('/quiz/draft', [QuizController::class, 'saveDraft'])->name('quiz.draft.save');
    Route::delete('/quiz/draft/{id}', [QuizController::class, 'deleteDraft'])->name('quiz.draft.delete');
    Route::get('/quiz/{id}/results', function ($id) {
        return view('quizzes.results', ['quizID' => $id]);
    })->name('quiz.results');
    Route::post('/quiz/schedule-submit', [QuizController::class, 'scheduleAssessment']);
   Route::get('/quiz/{id}/take', function ($id) {
    $quiz = \App\Models\Quiz::findOrFail($id);
    $now = now();
    $end = $quiz->StartTime->copy()->addMinutes($quiz->Duration);

    if ($quiz->StartTime > $now) {
        $status = 'upcoming';
    } elseif ($now <= $end) {
        $status = 'active';
    } else {
        $status = 'closed';
    }

    return view('quizzes.quiz-take', compact('quiz', 'status'));
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
Route::get('/quiz/{quizID}/my-review', [QuizEngineController::class, 'myReview'])
    ->middleware('auth')
    ->name('quiz.my-review');
    Route::get('/quiz/latest-results', [QuizEngineController::class, 'latestResults'])
    ->middleware('auth')
    ->name('quiz.latest-results');

// ->middleware('auth')
use App\Http\Controllers\AdminGroupController;
use App\Http\Controllers\AdminDashboardController;


Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/groups', [AdminGroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [AdminGroupController::class, 'store'])->name('groups.store');
    Route::put('/groups/{group}', [AdminGroupController::class, 'update'])->name('groups.update');
    Route::get('/statistics', [\App\Http\Controllers\Admin\AdminStatisticsController::class, 'index'])->name('statistics');
    Route::get('/statistics/export', [\App\Http\Controllers\Admin\AdminStatisticsController::class, 'export'])->name('statistics.export');

    Route::get('/lecturer-staff', [AdminLecturerStaffController::class, 'index'])->name('lecturer-staff.index');
    Route::post('/lecturer-staff', [AdminLecturerStaffController::class, 'store'])->name('lecturer-staff.store');
    Route::delete('/lecturer-staff/{staffId}', [AdminLecturerStaffController::class, 'destroy'])->name('lecturer-staff.destroy');
    Route::post('/lecturer-staff/{user}/promote', [AdminLecturerStaffController::class, 'promote'])->name('lecturer-staff.promote');
Route::post('/lecturer-staff/{user}/demote', [AdminLecturerStaffController::class, 'demote'])->name('lecturer-staff.demote');

    Route::get('/student-ids', [\App\Http\Controllers\AdminStudentIdController::class, 'index'])->name('student-ids.index');
    Route::post('/student-ids', [\App\Http\Controllers\AdminStudentIdController::class, 'store'])->name('student-ids.store');
    Route::delete('/student-ids/{studentId}', [\App\Http\Controllers\AdminStudentIdController::class, 'destroy'])->name('student-ids.destroy');
    Route::get('/blacklist', [\App\Http\Controllers\Admin\AdminBlacklistController::class, 'index'])->name('blacklist');
Route::post('/blacklist', [\App\Http\Controllers\Admin\AdminBlacklistController::class, 'store'])->name('blacklist.store');
Route::delete('/blacklist/{blacklist}', [\App\Http\Controllers\Admin\AdminBlacklistController::class, 'destroy'])->name('blacklist.destroy');
Route::post('/warning', [\App\Http\Controllers\Admin\AdminWarningController::class, 'store'])->name('warning.store');

    Route::get('/flagged-content', [\App\Http\Controllers\Admin\AdminFlaggedContentController::class, 'index'])->name('flagged-content.index');
    Route::post('/flagged-content/{post}/dismiss', [\App\Http\Controllers\Admin\AdminFlaggedContentController::class, 'dismiss'])->name('flagged-content.dismiss');
    Route::delete('/flagged-content/{post}', [\App\Http\Controllers\Admin\AdminFlaggedContentController::class, 'destroy'])->name('flagged-content.destroy');
    Route::post('/flagged-content/messages/{message}/dismiss', [\App\Http\Controllers\Admin\AdminFlaggedContentController::class, 'dismissMessage'])->name('flagged-content.messages.dismiss');
    Route::delete('/flagged-content/messages/{message}', [\App\Http\Controllers\Admin\AdminFlaggedContentController::class, 'destroyMessage'])->name('flagged-content.messages.destroy');
    Route::post('/flagged-content/replies/{reply}/dismiss', [\App\Http\Controllers\Admin\AdminFlaggedContentController::class, 'dismissReply'])->name('flagged-content.replies.dismiss');
    Route::delete('/flagged-content/replies/{reply}', [\App\Http\Controllers\Admin\AdminFlaggedContentController::class, 'destroyReply'])->name('flagged-content.replies.destroy');

});

Route::get('/notifications/poll', [DiscussionHubPageController::class, 'pollNotifications'])
    ->middleware('verified')->name('notifications.poll');

Route::patch('/notifications/{notification}/read', [DiscussionHubPageController::class, 'markNotificationRead'])
    ->middleware('verified')->name('notifications.read');

Route::patch('/notifications/read-all', [DiscussionHubPageController::class, 'markAllNotificationsRead'])
    ->middleware('verified')->name('notifications.read.all');
    Route::middleware('auth')->group(function () {
    Route::get('/groups/{groupId}/messages/{conversationId?}', [GroupChatController::class, 'index'])->name('student.messages');
    Route::post('/groups/{groupId}/messages', [GroupChatController::class, 'store'])->middleware('blacklist')->name('student.messages.store');
    Route::get('/group-messages/{message}/attachment', [AttachmentController::class, 'downloadMessage'])->name('student.messages.attachment');
    Route::patch('/group-messages/{message}', [GroupChatController::class, 'update'])->middleware('blacklist')->name('student.messages.update');
    Route::delete('/group-messages/{message}', [GroupChatController::class, 'destroy'])->name('student.messages.destroy');
});
