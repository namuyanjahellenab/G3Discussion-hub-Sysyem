<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Notification extends Model
{
    protected $table = 'Notification';
    protected $primaryKey = 'NotificationID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
     protected $fillable = [
        'UserID',
        'GroupID',
        'Message',
        'Status',
        'Type',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'GroupID', 'GroupID');
    }

    /**
     * Every place notifications are read for display (bell poll, dashboard
     * list, offline sync) must apply this - a "New quiz scheduled"/"Quiz
     * updated" notification is only ever still relevant if the recipient is
     * still, right now, in the 'groupstudent' table for that GroupID.
     * Checked live here (not just cleaned up once when a student leaves)
     * so it can't go stale again no matter which path removes them from the
     * group - GroupController::leave(), Api\GroupApiController::leave(), an
     * admin removing them, or any future one that forgets to.
     */
    public function scopeExcludingStaleGroupNotifications($query)
    {
        return $query->where(function ($q) {
            $q->where('Type', '!=', 'Quiz Announcement')
              ->orWhereNull('GroupID')
              ->orWhereExists(function ($sub) {
                  $sub->select(DB::raw(1))
                      ->from('groupstudent')
                      ->whereColumn('groupstudent.GroupID', 'Notification.GroupID')
                      ->whereColumn('groupstudent.UserID', 'Notification.UserID');
              });
        });
    }
}
