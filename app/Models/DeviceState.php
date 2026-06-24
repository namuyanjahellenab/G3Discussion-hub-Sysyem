<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceState extends Model
{
    protected $table = 'DeviceState';
    protected $primaryKey = 'DeviceID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
}
