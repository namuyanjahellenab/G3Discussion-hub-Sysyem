<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
 const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

   protected $table = 'message';
protected $primaryKey = 'MessageID';

protected $fillable = ['TopicID', 'user_id', 'ConversationID', 'body', 'is_spam'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'UserID');
    }   
public function topic()
{
    return $this->belongsTo(Topic::class);
}

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'ConversationID', 'ConversationID');
    }
}
