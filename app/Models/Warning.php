<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warning extends Model
{
    protected $table = 'Warning';
    protected $primaryKey = 'WarningID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = [
        'UserID',
        'WarningNo',
        'Reason',
        'ExpiryDate',
    ];

    protected $casts = [
        'ExpiryDate' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }
}