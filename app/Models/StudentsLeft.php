<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentsLeft extends Model
{
    protected $table = 'Studentsleft';
    public $timestamps = false;

    protected $fillable = [
        'UserID',
        'UserName',
        'Email',
        'StudentIDNumber',
        'GroupID',
        'GroupName',
        'TotalMarks',
        'PostCount',
        'ReplyCount',
        'ParticipationScore',
        'LeftAt',
    ];

    protected $casts = [
        'LeftAt' => 'datetime',
    ];
}
