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
        // One query for every group's membership instead of one exists()
        // query per group (was N+1 - trendingGroups()+browsableGroups()
        // together were issuing 10+ near-identical queries on a single page
        // load for a page with barely a dozen groups, scaling worse as the
        // groups table grows).
        $joinedGroupIds = GroupStudent::where('UserID', $userId)
            ->whereIn('GroupID', $groups->pluck('GroupID'))
            ->pluck('GroupID');

        return $groups->map(function ($group) use ($joinedGroupIds) {
            $group->userJoined = $joinedGroupIds->contains($group->GroupID);

            // CourseCode is a persisted column now (see the
            // add_course_code_to_groups_table migration) - only fall back to
            // deriving one on the fly for a group that somehow still has
            // none stored, instead of always recomputing it.
            if (empty($group->CourseCode)) {
                $group->CourseCode = $this->deriveGroupCode($group);
            }

            return $group;
        });
    }

    /**
     * Formula used to auto-assign a group's CourseCode at creation time
     * (see AdminGroupController::store) and to backfill any row that
     * predates the CourseCode column: initials of the group's name (or its
     * first three letters for a single-word name) plus a number derived
     * from the group's ID. Cosmetic - not tied to any real institutional
     * course catalog.
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
