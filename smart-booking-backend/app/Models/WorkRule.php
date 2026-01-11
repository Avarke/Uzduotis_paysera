<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkRule extends Model
{
    protected $table = 'work_rules';

    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
        'slot_minutes',
    ];
}
