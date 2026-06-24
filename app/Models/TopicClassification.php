<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicClassification extends Model
{
    protected $table = 'TopicClassification';
    protected $primaryKey = 'ClassificationID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
}
