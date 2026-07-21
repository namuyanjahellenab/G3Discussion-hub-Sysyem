<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\GroupStudent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class Group extends Model
{
    protected $table = 'Group';
    protected $primaryKey = 'GroupID';
     protected $fillable = ['GroupName', 'Description', 'CreatedBy', 'CourseCode'];
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    public function students(): HasMany
    {
        return $this->hasMany(GroupStudent::class, 'GroupID', 'GroupID');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'GroupStudent', 'GroupID', 'UserID', 'GroupID', 'UserID')
            ->withPivot(['StudentID', 'Status', 'CreatedAt', 'UpdatedAt']);
    }

    /**
     * Active membership (with user loaded) for exclude pickers, chat member
     * lists, etc. — read on nearly every "create topic"/"create announcement"
     * page load but only changes when someone joins/leaves, so it's cached
     * and busted from GroupStudent's model events instead of every request.
     */
    public function activeMembers()
    {
        return Cache::remember('group_active_members:' . $this->GroupID, 300, function () {
            return $this->students()->where('Status', 'active')->with('user')->get();
        });
    }

    public static function forgetMembersCache(int $groupId): void
    {
        Cache::forget('group_active_members:' . $groupId);
    }
}
