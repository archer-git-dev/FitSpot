<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarInterval extends Model
{
    protected $fillable = ['start', 'end'];

    public $timestamps = false;
}
