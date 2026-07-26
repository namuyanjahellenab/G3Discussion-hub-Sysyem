<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionOption extends Model
{
    protected $table = 'question_options';
    protected $primaryKey = 'QuestionOptionID';
    public $timestamps = false;

    protected $fillable = ['QuestionID', 'OptionText', 'Position'];

    public function question()
    {
        return $this->belongsTo(Question::class, 'QuestionID', 'QuestionID');
    }
}
