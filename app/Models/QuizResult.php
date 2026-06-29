<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuizResult extends Model {
    protected $table      = 'QuizResult';
    protected $primaryKey = 'ResultID';
    public $timestamps    = false;

    protected $fillable = [
        'QuizID', 'UserID', 'Score', 'SubmissionTime', 'IsAutoSubmit'
    ];

    public function answers() {
        return $this->hasMany(Answer::class, 'ResultID', 'ResultID');
    }
}