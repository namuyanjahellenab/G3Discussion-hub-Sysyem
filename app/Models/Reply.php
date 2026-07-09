<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reply extends Model
{
    protected $table = 'Reply';
    protected $primaryKey = 'ReplyID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    // added QuotedReplyID
    protected $fillable = ['PostID', 'UserID', 'ReplyContent', 'IsAccepted', 'QuotedReplyID'];

    public function author()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'PostID', 'PostID');
    }

    // NEW: the reply this one is quoting, if any
    public function quotedReply()
    {
        return $this->belongsTo(Reply::class, 'QuotedReplyID', 'ReplyID');
    }
}
