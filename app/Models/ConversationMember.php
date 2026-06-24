<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationMember extends Model
{
    protected $table = 'ConversationMember';
    protected $primaryKey = 'MemberID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
}
