<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;

class WorkingInterval extends Model
{
    protected $table = 'working_intervals';

    protected $fillable = ['workspace_id', 'day', 'start', 'end'];

    public $timestamps = false;
}
