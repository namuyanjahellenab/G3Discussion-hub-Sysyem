<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    protected $table = 'Recommendation';
    protected $primaryKey = 'RecommendationID';
    protected $fillable = ['UserID', 'TopicID', 'RelevanceScore'];
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'TopicID', 'TopicID');
    }
}
