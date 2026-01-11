<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientBooking extends Model
{
    protected $table = 'client_bookings';

    protected $fillable = [
        'service_id',
        'date',
        'start_time',
        'end_time',
        'client_email',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
