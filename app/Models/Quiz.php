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
    'UpdatedAt' => 'datetime',
];

    protected $fillable = [
        'LecturerID', 'GroupID', 'Title', 'StartTime', 'Duration', 'Status',
        'CreatedAt', 'UpdatedAt'
    ];

    public function questions() {
        return $this->hasMany(Question::class, 'QuizID', 'QuizID');
    }

    public function group() {
        return $this->belongsTo(Group::class, 'GroupID', 'GroupID');
    }

    public function results() {
        return $this->hasMany(QuizResult::class, 'QuizID', 'QuizID');
    }
}