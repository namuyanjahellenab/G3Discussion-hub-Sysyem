<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicExclusion extends Model
{
    protected $table = 'topic_exclusions';
    protected $primaryKey = 'TopicExclusionID';
    public $timestamps = false;

    protected $fillable = ['TopicID', 'UserID'];

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }
}
