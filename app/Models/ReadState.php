<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadState extends Model
{
    protected $table = 'read_states';
    protected $primaryKey = 'ReadStateID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = ['UserID', 'EntityType', 'EntityID', 'LastReadItemId'];

    /** 0 means "never visited" - see DatabaseManager.getLastReadItemId()'s
     *  desktop-side equivalent for how callers should treat that. */
    public static function lastRead(int $userId, string $entityType, int $entityId): int
    {
        return (int) (static::where('UserID', $userId)
            ->where('EntityType', $entityType)
            ->where('EntityID', $entityId)
            ->value('LastReadItemId') ?? 0);
    }

    /** Never regresses - a stale/smaller latestItemId must not un-mark
     *  something already known to be read. */
    public static function markRead(int $userId, string $entityType, int $entityId, int $latestItemId): void
    {
        if ($latestItemId <= 0) {
            return;
        }

        $row = static::firstOrNew([
            'UserID' => $userId,
            'EntityType' => $entityType,
            'EntityID' => $entityId,
        ]);
        $row->LastReadItemId = max($row->LastReadItemId ?? 0, $latestItemId);
        $row->save();
    }
}
