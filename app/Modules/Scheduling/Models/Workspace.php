<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $table = 'workspaces';

    protected $fillable = ['owner_id', 'name', 'slug', 'timezone', 'description', 'phone', 'location_name', 'address', 'photo_path'];

    public function intervals()
    {
        return $this->hasMany(WorkingInterval::class)->orderBy('day')->orderBy('start');
    }

    public function calendarDays()
    {
        return $this->hasMany(CalendarDay::class)->orderBy('date');
    }

    public function rules()
    {
        return $this->hasOne(BookingRules::class);
    }

    public function services()
    {
        return $this->hasMany(TrainingService::class)->orderBy('id');
    }
}
