<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInactivityWarning extends Model
{
    protected $table = 'user_inactivity_warnings';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = [
        'UserID',
        'FirstWarningAt',
        'SecondWarningAt',
    ];

    protected $casts = [
        'FirstWarningAt' => 'datetime',
        'SecondWarningAt' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }
}
