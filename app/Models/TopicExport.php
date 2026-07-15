<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicExport extends Model
{
    protected $table = 'topic_exports';
    protected $primaryKey = 'TopicExportID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = ['TopicID', 'UserID', 'Status', 'FilePath'];

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'TopicID', 'TopicID');
    }
}
