<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reply extends Model
{
    protected $table = 'Reply';
    protected $primaryKey = 'ReplyID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    public function author()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'PostID', 'PostID');
    }
}
