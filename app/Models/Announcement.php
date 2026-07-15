<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $table = 'announcements';
    protected $primaryKey = 'AnnouncementID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = null;

    protected $fillable = ['AuthorID', 'GroupID', 'Message'];

    public function author()
    {
        return $this->belongsTo(User::class, 'AuthorID', 'UserID');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'GroupID', 'GroupID');
    }

    public function exclusions()
    {
        return $this->hasMany(AnnouncementExclusion::class, 'AnnouncementID', 'AnnouncementID');
    }
}
