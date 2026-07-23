<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageFlag extends Model
{
    protected $table = 'message_flags';
    protected $primaryKey = 'MessageFlagID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = null;

    protected $fillable = ['MessageID', 'FlaggedByUserID', 'Reason'];

    public function message()
    {
        return $this->belongsTo(Message::class, 'MessageID', 'MessageID');
    }

    public function flaggedBy()
    {
        return $this->belongsTo(User::class, 'FlaggedByUserID', 'UserID');
    }
}
