<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $table = 'Topic'; 
    protected $primaryKey = 'TopicID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    public function creator()
    {
        return $this->belongsTo(User::class, 'CreatedBy', 'UserID');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'TopicID', 'TopicID');
    }
}
