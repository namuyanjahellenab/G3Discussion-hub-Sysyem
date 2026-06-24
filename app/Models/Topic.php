<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $table = 'Topic'; 
    protected $primaryKey = 'TopicID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

public function messages()
{
    return $this->hasMany(Message::class);
}
public function excludedUsers()
{
    return $this->belongsToMany(User::class, 'topic_exclusions');
}
}