<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use App\Models\Topic;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalGroups = Group::count();
        $totalUsers = User::count();
        $totalStudents = User::where('Role', 'Student')->count();
        $totalLecturers = User::where('Role', 'Lecturer')->count();

        // TODO: replace once the Blacklist feature (UserBlacklist table) is built
        $blacklistedCount = 0;

        // TODO: replace once Announcements has its own model/table
        $announcementsCount = 0;

        // Recent topic activity across all groups, used to seed the live activity log
        $recentTopics = Topic::with('creator', 'group')
            ->latest('CreatedAt')
            ->take(6)
            ->get();

        return view('admin.dashboard.index', compact(
            'totalGroups', 'totalUsers', 'totalStudents', 'totalLecturers',
            'blacklistedCount', 'announcementsCount', 'recentTopics'
        ));
    }
}