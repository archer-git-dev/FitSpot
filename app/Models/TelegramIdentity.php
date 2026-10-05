<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramIdentity extends Model
{
    protected $fillable = ['user_id', 'subject'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
