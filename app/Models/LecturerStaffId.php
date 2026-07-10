<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LecturerStaffId extends Model
{
    public function linkedUser()
{
    return $this->belongsTo(\App\Models\User::class, 'LinkedUserID', 'UserID');
}
    protected $table = 'LecturerStaffIDs';
    protected $primaryKey = 'StaffCodeID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = [
        'StaffIDNumber',
        'IsUsed',
        'LinkedUserID',
    ];
}