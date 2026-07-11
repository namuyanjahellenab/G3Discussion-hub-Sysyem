<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupStudent extends Model
{
   
    protected $table = 'groupstudent';
    protected $primaryKey = 'StudentID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = ['GroupID', 'UserID', 'Status', 'LastViewedAt'];

    public function group()
    {
        return $this->belongsTo(Group::class, 'GroupID', 'GroupID');
    }

    public function user()
{
    return $this->belongsTo(User::class, 'UserID', 'UserID');
}
}
