<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
 const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $table = 'Message'; // Specify the table name
    protected $primaryKey = 'MessageID'; // Specify the primary key

    protected $fillable = [
        'TopicID',
        'user_id',
        'body',
        'is_spam',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'UserID');
    }   
public function topic()
{
    return $this->belongsTo(Topic::class);
}
}
