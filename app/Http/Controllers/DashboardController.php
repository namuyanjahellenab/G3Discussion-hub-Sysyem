<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->Role === 'Lecturer') {
            return $this->lecturer();
        }

        // Falls through to the existing default view for everyone else
        return view('dashboard');
    }

    protected function lecturer()
    {
        $lecturerId = Auth::id();

        $quizzes = Quiz::where('LecturerID', $lecturerId)
            ->orderByDesc('StartTime')
            ->get();

        $now = now();

        $upcoming = $quizzes->filter(fn($q) => $q->StartTime > $now)->count();
        $active   = $quizzes->filter(fn($q) =>
            $q->StartTime <= $now && $now <= $q->StartTime->copy()->addMinutes($q->Duration)
        )->count();
        $closed   = $quizzes->filter(fn($q) =>
            $q->StartTime->copy()->addMinutes($q->Duration) < $now
        )->count();

        $recentResults = QuizResult::whereIn('QuizID', $quizzes->pluck('QuizID'))
            ->orderByDesc('SubmissionTime')
            ->take(10)
            ->get();

        return view('lecturer.dashboard', compact('quizzes', 'upcoming', 'active', 'closed', 'recentResults'));
    }
}