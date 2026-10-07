<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarDay extends Model
{
    protected $fillable = ['workspace_id', 'date'];

    public function intervals()
    {
        return $this->hasMany(CalendarInterval::class)->orderBy('start');
    }
}
