<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model {
    protected $table      = 'Answer';
    protected $primaryKey = 'AnswerID';
    public $timestamps    = false;

    protected $fillable = [
        'QuestionID', 'ResultID', 'UserID', 'ResponseText', 'IsCorrect'
    ];
}
