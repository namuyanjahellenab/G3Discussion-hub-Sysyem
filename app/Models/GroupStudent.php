<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupStudent extends Model
{
    protected $table = 'GroupStudent';
    protected $primaryKey = 'StudentID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
}
