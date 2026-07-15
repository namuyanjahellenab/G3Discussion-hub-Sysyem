<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReplyFlag extends Model
{
    protected $table = 'reply_flags';
    protected $primaryKey = 'ReplyFlagID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = null;

    protected $fillable = ['ReplyID', 'FlaggedByUserID', 'Reason'];

    public function reply()
    {
        return $this->belongsTo(Reply::class, 'ReplyID', 'ReplyID');
    }

    public function flaggedBy()
    {
        return $this->belongsTo(User::class, 'FlaggedByUserID', 'UserID');
    }
}
