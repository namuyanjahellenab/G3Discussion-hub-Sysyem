<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'Post';
    protected $primaryKey = 'PostID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    public function author()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'TopicID', 'TopicID');
    }

    public function replies()
    {
        return $this->hasMany(Reply::class, 'PostID', 'PostID');
    }

    public function parent()
    {
        return $this->belongsTo(Post::class, 'ParentPostID', 'PostID');
    }
}
