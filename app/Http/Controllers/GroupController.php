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
    public function index()
    {
        $user = Auth::user();

        $groups = Group::withCount(['students as member_count'])
            ->get()
            ->map(function ($group) use ($user) {
                $group->userJoined = GroupStudent::where('GroupID', $group->GroupID)
                    ->where('UserID', $user->UserID)
                    ->exists();

                return $group;
            });

        $suggestedGroups = $this->suggestedGroups($user);

        return view('groups.index', compact('groups', 'suggestedGroups'));
    }

    /**
     * Groups the user hasn't joined yet, ranked by member count and recent
     * activity. Prefers the ML gateway's live interaction ranking; falls
     * back to the same ranking computed locally when the gateway is
     * unreachable.
     */
    private function suggestedGroups($user)
    {
        $joinedGroupIds = GroupStudent::where('UserID', $user->UserID)->pluck('GroupID')->all();

        $gatewayResult = app(MlGatewayClient::class)->recommendGroups($user->UserID, $joinedGroupIds);
        $suggestedGroupIds = collect($gatewayResult['SuggestedGroups'] ?? [])->pluck('GroupID');

        if ($suggestedGroupIds->isNotEmpty()) {
            return Group::withCount(['students as member_count'])
                ->whereIn('GroupID', $suggestedGroupIds)
                ->get()
                ->sortBy(fn ($group) => $suggestedGroupIds->search($group->GroupID))
                ->values();
        }

        return Group::withCount(['students as member_count'])
            ->whereDoesntHave('users', fn ($q) => $q->where('UserID', $user->UserID))
            ->orderByDesc('member_count')
            ->limit(5)
            ->get();
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
