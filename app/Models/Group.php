<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\GroupStudent;
use App\Models\User;

class Group extends Model
{
    protected $table = 'Group';
    protected $primaryKey = 'GroupID';
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
}
