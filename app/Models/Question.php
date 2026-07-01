<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Question extends Model {
    protected $table      = 'Question';
    protected $primaryKey = 'QuestionID';
    public $timestamps    = false;

    protected $fillable = [
        'QuizID', 'QuestionText', 'QuestionType', 'Options', 'CorrectAnswer', 'Marks'
    ];

    protected $casts = [
        'Options' => 'array',
        'Marks'   => 'float',
    ];
}