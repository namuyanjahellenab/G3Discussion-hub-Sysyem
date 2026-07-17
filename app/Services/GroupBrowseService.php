<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupStudent;
use Illuminate\Support\Collection;

// Powers the "View Discussion Groups" browsing page (web GroupController::index()
// and the desktop's matching group-browse screen) so trending ranking and
// course-code derivation can't drift between the two the way the desktop's
// old hardcoded 4-entry course-code switch did.
class GroupBrowseService
{
    /**
     * Every group ranked by member count + weighted recent activity, whether
     * the user has joined it or not - trending is an objective "what's
     * popular right now" signal, not a personalized "you haven't joined
     * this" suggestion. Prefers the ML gateway's live interaction ranking;
     * falls back to the same ranking computed locally when unreachable.
     */
    public function trendingGroups(int $userId): Collection
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

        return $this->annotate($groups, $userId);
    }

    /**
     * All groups matching the search term (or all groups when empty),
     * excluding anything already shown as trending so groups don't appear
     * twice on the page.
     */
    public function browsableGroups(int $userId, string $search, Collection $excludeGroupIds): Collection
    {
        $groups = Group::withCount(['students as member_count'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('GroupName', 'like', "%{$search}%")
                        ->orWhere('Description', 'like', "%{$search}%");
                });
            })
            ->when($excludeGroupIds->isNotEmpty(), fn ($q) => $q->whereNotIn('GroupID', $excludeGroupIds))
            ->get();

        return $this->annotate($groups, $userId);
    }

    private function annotate(Collection $groups, int $userId): Collection
    {
        return $groups->map(function ($group) use ($userId) {
            $group->userJoined = GroupStudent::where('GroupID', $group->GroupID)
                ->where('UserID', $userId)
                ->exists();
            $group->CourseCode = $this->deriveGroupCode($group);

            return $group;
        });
    }

    /**
     * The Group table has no real course-code column, so this derives a
     * short, distinct-looking code from the group's name (initials for
     * multi-word names, first letters for single-word names) plus a number
     * from the group's ID. Cosmetic only - not tied to any real course.
     */
    public function deriveGroupCode(Group $group): string
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
}
