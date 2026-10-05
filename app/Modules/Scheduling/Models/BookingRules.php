<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRules extends Model
{
    protected $table = 'booking_rules';

    protected $fillable = ['workspace_id', 'buffer_before', 'buffer_after', 'lead_minutes', 'horizon_days', 'slot_step', 'cancel_minutes', 'reschedule_minutes'];
}
