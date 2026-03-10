<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceCommandAudit extends Model
{
    protected $fillable = [
        'device_id',
        'command',
        'status',
        'start_time',
        'end_time', // ← Add this line
    ];

}
