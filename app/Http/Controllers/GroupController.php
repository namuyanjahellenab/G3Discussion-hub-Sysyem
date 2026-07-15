<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupStudent;
use App\Services\MlGatewayClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $search = trim((string) $request->query('search', ''));

        // Trending is a platform-wide popularity signal, not part of the
        // search results — showing it alongside a specific query reads as
        // "why is this trending group here, I searched for something else".
        $trendingGroups = $search === '' ? $this->trendingGroups($user) : collect();
        $trendingGroupIds = $trendingGroups->pluck('GroupID');

        // Exclude anything already shown as trending so groups don't appear
        // twice on the page.
        $groups = Group::withCount(['students as member_count'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('GroupName', 'like', "%{$search}%")
                        ->orWhere('Description', 'like', "%{$search}%");
                });
            })
            ->when($trendingGroupIds->isNotEmpty(), fn ($q) => $q->whereNotIn('GroupID', $trendingGroupIds))
            ->get()
            ->map(function ($group) use ($user) {
                $group->userJoined = GroupStudent::where('GroupID', $group->GroupID)
                    ->where('UserID', $user->UserID)
                    ->exists();
                $group->CourseCode = $this->deriveGroupCode($group);

                return $group;
            });

        return view('groups.index', compact('groups', 'trendingGroups', 'search'));
    }

    /**
     * Every group ranked by member count + weighted recent activity, whether
     * the user has joined it or not — trending is an objective "what's
     * popular right now" signal, not a personalized "you haven't joined
     * this" suggestion. Prefers the ML gateway's live interaction ranking;
     * falls back to the same ranking computed locally when unreachable.
     */
    private function trendingGroups($user)
    {
        $gatewayResult = app(MlGatewayClient::class)->trendingGroups();
        $trendingGroupIds = collect($gatewayResult['TrendingGroups'] ?? [])->pluck('GroupID');

        $groups = $trendingGroupIds->isNotEmpty()
            ? Group::withCount(['students as member_count'])
                ->whereIn('GroupID', $trendingGroupIds)
                ->get()
                ->sortBy(fn ($group) => $trendingGroupIds->search($group->GroupID))
                ->values()
            : Group::withCount(['students as member_count'])
                ->having('member_count', '>', 0)
                ->orderByDesc('member_count')
                ->limit(5)
                ->get();

        return $groups->map(function ($group) use ($user) {
            $group->userJoined = GroupStudent::where('GroupID', $group->GroupID)
                ->where('UserID', $user->UserID)
                ->exists();
            $group->CourseCode = $this->deriveGroupCode($group);

            return $group;
        });
    }

    /**
     * The Group table has no real course-code column, so this derives a
     * short, distinct-looking code from the group's name (initials for
     * multi-word names, first letters for single-word names) plus a number
     * from the group's ID. Cosmetic only — not tied to any real course.
     */
    private function deriveGroupCode(Group $group): string
    {
        $stopWords = ['and', 'of', 'the', 'for', 'group', 'a', 'an'];
        $words = collect(preg_split('/\s+/', trim($group->GroupName)))
            ->filter()
            ->reject(fn ($word) => in_array(strtolower($word), $stopWords))
            ->values();

        if ($words->count() >= 2) {
            $letters = $words->take(3)->map(fn ($word) => strtoupper(substr($word, 0, 1)))->implode('');
        } elseif ($words->count() === 1) {
            $letters = strtoupper(substr($words->first(), 0, 3));
        } else {
            $letters = 'GRP';
        }

        $number = 100 + ($group->GroupID % 900);

        return $letters . $number;
    }

    public function join(Group $group): RedirectResponse
    {
        $user = Auth::user();

        GroupStudent::firstOrCreate([
            'GroupID' => $group->GroupID,
            'UserID' => $user->UserID,
        ], [
            'Status' => 'active',
        ]);

        return redirect()->route('groups.index');
    }

    public function leave(Group $group): RedirectResponse
    {
        $user = Auth::user();

        GroupStudent::where('GroupID', $group->GroupID)
            ->where('UserID', $user->UserID)
            ->delete();

        return redirect()->route('groups.index');
    }

    public function forum(Group $group)
    {
        return view('groups.forum', compact('group'));
    }
}
