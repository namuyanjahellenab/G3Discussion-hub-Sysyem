<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupStudent extends Model
{
   
    protected $table = 'GroupStudent';
    protected $primaryKey = 'StudentID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = ['GroupID', 'UserID', 'Status', 'LastViewedAt'];

    protected static function booted(): void
    {
        static::saved(fn ($membership) => Group::forgetMembersCache($membership->GroupID));
        static::deleted(fn ($membership) => Group::forgetMembersCache($membership->GroupID));
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'GroupID', 'GroupID');
    }

    public function user()
{
    return $this->belongsTo(User::class, 'UserID', 'UserID');
}
}
