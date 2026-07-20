<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentId extends Model
{
    public function linkedUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'LinkedUserID', 'UserID');
    }

    protected $table = 'StudentIDs';
    protected $primaryKey = 'StudentCodeID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = [
        'StudentIDNumber',
        'IsUsed',
        'LinkedUserID',
    ];
}
