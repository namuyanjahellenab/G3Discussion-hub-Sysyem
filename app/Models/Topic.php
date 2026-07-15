<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $table = 'Topic';
    protected $primaryKey = 'TopicID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = ['Title', 'GroupID', 'CreatedBy', 'Status', 'IsPinned', 'Category'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'CreatedBy', 'UserID');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'TopicID', 'TopicID');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'GroupID', 'GroupID');
    }

    public function classification()
    {
        return $this->hasOne(TopicClassification::class, 'TopicID', 'TopicID');
    }

    public function exclusions()
    {
        return $this->hasMany(TopicExclusion::class, 'TopicID', 'TopicID');
    }

    public function replies()
    {
        return $this->hasManyThrough(Reply::class, Post::class, 'TopicID', 'PostID', 'TopicID', 'PostID');
    }

    public function scopeVisibleTo($query, int $userId)
    {
        return $query->whereNotIn('TopicID', TopicExclusion::where('UserID', $userId)->pluck('TopicID'));
    }
}
