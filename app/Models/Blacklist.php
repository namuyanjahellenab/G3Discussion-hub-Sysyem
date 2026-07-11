<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    protected $table = 'Blacklist';
    protected $primaryKey = 'BlacklistID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = [
        'UserID',
        'StartDate',
        'EndDate',
        'Reason',
        'Type',
        'IssuedBy',
    ];

    protected $casts = [
        'StartDate' => 'datetime',
        'EndDate' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'IssuedBy', 'UserID');
    }

    public function scopeActive($query)
    {
        return $query->where('EndDate', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('EndDate', '<', now());
    }
}