<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostFlag extends Model
{
    protected $table = 'post_flags';
    protected $primaryKey = 'PostFlagID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = null;

    protected $fillable = ['PostID', 'FlaggedByUserID', 'Reason'];

    public function post()
    {
        return $this->belongsTo(Post::class, 'PostID', 'PostID');
    }

    public function flaggedBy()
    {
        return $this->belongsTo(User::class, 'FlaggedByUserID', 'UserID');
    }
}
