<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model {
    protected $table      = 'Quiz';
    protected $primaryKey = 'QuizID';
    public $timestamps    = false;
    protected $casts = [
    'StartTime' => 'datetime',
    'CreatedAt' => 'datetime',
];

    protected $fillable = [
        'LecturerID', 'Title', 'StartTime', 'Duration', 'TargetCategory'
    ];

    public function questions() {
        return $this->hasMany(Question::class, 'QuizID', 'QuizID');
    }

    public function results() {
        return $this->hasMany(QuizResult::class, 'QuizID', 'QuizID');
    }
}