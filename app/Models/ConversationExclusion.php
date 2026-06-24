<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationExclusion extends Model
{
    protected $table = 'ConversationExclusion';
    protected $primaryKey = 'ExclusionID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
}
