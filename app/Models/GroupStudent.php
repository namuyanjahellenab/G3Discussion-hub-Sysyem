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

    protected $fillable = [
        'GroupID',
        'UserID',
        'Status',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'GroupID', 'GroupID');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }
}
