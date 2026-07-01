<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizResult extends Model
{
    protected $table = 'QuizResult';
    protected $primaryKey = 'ResultID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = [
        'QuizID', 'UserID', 'Score', 'SubmissionTime', 'IsAutoSubmit'
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'QuizID', 'QuizID');
    }

    public function answers()
    {
        return $this->hasMany(Answer::class, 'ResultID', 'ResultID');
    }
}
