<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'Notification';
    protected $primaryKey = 'NotificationID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
     protected $fillable = [
        'UserID',
        'Message',
        'Status',
        'Type',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }
}
