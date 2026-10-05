<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingService extends Model
{
    protected $table = 'training_services';

    protected $fillable = ['workspace_id', 'name', 'description', 'type', 'format', 'duration', 'price_kopecks', 'capacity', 'location', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
