<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $table = 'Conversation';
    protected $primaryKey = 'ConversationID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = ['Type', 'CreatedBy', 'group_id'];

    public function members()
    {
        return $this->hasMany(ConversationMember::class, 'ConversationID');
    }

    public function exclusions()
    {
        return $this->hasMany(ConversationExclusion::class, 'ConversationID');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'ConversationID');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'GroupID');
    }
}