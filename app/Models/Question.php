<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Question extends Model {
    protected $table      = 'Question';
    protected $primaryKey = 'QuestionID';
    public $timestamps    = false;

    protected $fillable = [
        'QuizID', 'QuestionText', 'QuestionType', 'CorrectAnswer', 'Marks'
    ];

    protected $casts = [
        'Marks'   => 'float',
    ];

    // Accessors aren't included in toArray()/toJson()/@json() unless listed
    // here - schedule.blade.php:868 does @json($draft) (the whole Quiz,
    // including its questions relation) to hand a draft to the JS form, so
    // without this, every question's Options would silently vanish from
    // that payload despite the accessor working fine when accessed directly
    // in PHP.
    protected $appends = ['Options'];

    /**
     * Named questionOptions(), not options() - Eloquent's accessor lookup
     * studly-cases the attribute key ('Options' and 'options' both resolve
     * to the same "getOptionsAttribute" name), so a relation literally named
     * options() would collide with the accessor below and call itself.
     */
    public function questionOptions()
    {
        return $this->hasMany(QuestionOption::class, 'QuestionID', 'QuestionID')->orderBy('Position');
    }

    /**
     * Preserves the exact external shape every existing caller already
     * expects ($question->Options as a plain array of strings) - the only
     * thing that changed is where it's actually stored. Reads off the
     * already-loaded relation when eager-loaded (no extra query); lazy-loads
     * it otherwise.
     */
    public function getOptionsAttribute()
    {
        return $this->questionOptions->sortBy('Position')->pluck('OptionText')->values()->all();
    }
}