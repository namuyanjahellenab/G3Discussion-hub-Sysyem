<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ParticipationCriteria extends Model
{
    protected $table = 'participation_criteria';
    protected $primaryKey = 'ParticipationCriteriaID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = ['GroupID', 'PointsPerPost', 'PointsPerReply', 'PointsPerAcceptedAnswer'];

    protected static function booted(): void
    {
        // forGroup() is read on every single post/reply — cache it, but bust
        // on any write so an admin changing point values takes effect
        // immediately rather than up to 5 minutes later.
        static::saved(fn ($criteria) => self::forgetCache($criteria->GroupID));
        static::deleted(fn ($criteria) => self::forgetCache($criteria->GroupID));
    }

    private static function forgetCache(?int $groupId): void
    {
        Cache::forget('participation_criteria:' . ($groupId ?? 'default'));
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'GroupID', 'GroupID');
    }

    /**
     * The criteria that applies to a group: its own row if configured,
     * otherwise the platform-wide default (GroupID = null).
     */
    public static function forGroup(?int $groupId): self
    {
        return Cache::remember('participation_criteria:' . ($groupId ?? 'default'), 300, function () use ($groupId) {
            return static::where('GroupID', $groupId)->first()
                ?? static::whereNull('GroupID')->first()
                ?? new static(['PointsPerPost' => 2, 'PointsPerReply' => 1, 'PointsPerAcceptedAnswer' => 0]);
        });
    }
}
