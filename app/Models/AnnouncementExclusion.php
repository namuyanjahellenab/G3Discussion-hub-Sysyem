<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementExclusion extends Model
{
    protected $table = 'announcement_exclusions';
    protected $primaryKey = 'AnnouncementExclusionID';
    public $timestamps = false;

    protected $fillable = ['AnnouncementID', 'UserID'];

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }
}
