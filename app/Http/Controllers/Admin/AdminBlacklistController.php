<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blacklist;
use App\Models\Warning;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminBlacklistController extends Controller
{
    public function index(Request $request)
    {
        $showExpired = $request->boolean('show_expired');

        $query = Blacklist::with(['user', 'issuer'])->orderByDesc('CreatedAt');

        if (!$showExpired) {
            $query->active();
        }

        $blacklist = $query->paginate(10)->withQueryString();

        // Users eligible to be manually blacklisted (not already actively blacklisted)
        $activelyBlacklistedIds = Blacklist::active()->pluck('UserID');
        $users = User::whereNotIn('UserID', $activelyBlacklistedIds)
            ->orderBy('UserName')
            ->get();

        // Computed activity log: recent blacklist + warning events, merged and sorted
        $blacklistEvents = Blacklist::with('user')
            ->orderByDesc('CreatedAt')
            ->limit(10)
            ->get()
            ->map(function ($b) {
                return [
                    'tag' => $b->Type === 'Auto' ? 'Auto' : 'Admin',
                    'text' => ($b->Type === 'Auto' ? "{$b->user?->UserName} blacklisted — {$b->Reason}" : "{$b->user?->UserName} manually blacklisted for {$b->Reason}"),
                    'time' => $b->CreatedAt,
                ];
            });

        $warningEvents = Warning::with('user')
            ->orderByDesc('CreatedAt')
            ->limit(10)
            ->get()
            ->map(function ($w) {
                return [
                    'tag' => 'Auto',
                    'text' => "Warning {$w->WarningNo} sent to {$w->user?->UserName}" . ($w->Reason ? " — {$w->Reason}" : ''),
                    'time' => $w->CreatedAt,
                ];
            });

        $activityLog = $blacklistEvents->concat($warningEvents)
            ->sortByDesc('time')
            ->take(8)
            ->values();

        return view('admin.blacklist.index', compact('blacklist', 'users', 'showExpired', 'activityLog'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'UserID' => 'required|exists:user,UserID',
            'Reason' => 'required|string|max:250',
            'StartDate' => 'required|date',
            'EndDate' => 'required|date|after:StartDate',
        ]);

        Blacklist::create([
            'UserID' => $validated['UserID'],
            'Reason' => 'Manual - ' . $validated['Reason'],
            'StartDate' => $validated['StartDate'],
            'EndDate' => $validated['EndDate'],
            'Type' => 'Manual',
            'IssuedBy' => Auth::id(),
        ]);

        return redirect()->route('admin.blacklist')->with('success', 'User blacklisted successfully.');
    }

    public function destroy(Blacklist $blacklist)
    {
        // Mark as expired now rather than hard-delete, preserving history for the
        // "show expired / historical records" toggle.
        $blacklist->update(['EndDate' => now()]);

        return redirect()->route('admin.blacklist')->with('success', 'Blacklist entry removed.');
    }
}