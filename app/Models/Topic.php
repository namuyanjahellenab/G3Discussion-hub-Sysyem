<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    //

public function messages()
{
    return $this->hasMany(Message::class);
}
public function excludedUsers()
{
    return $this->belongsToMany(User::class, 'topic_exclusions');
}
}