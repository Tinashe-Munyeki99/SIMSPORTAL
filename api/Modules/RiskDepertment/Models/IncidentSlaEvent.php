<?php

namespace Modules\RiskDepertment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

// use Modules\RiskDepertment\Database\Factories\IncidentSlaEventFactory;

class IncidentSlaEvent extends Model
{
    use HasFactory,softDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id','site_id','incident_report_id','event_type','minutes_value','meta'
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (!$m->id) $m->id = (string) \Illuminate\Support\Str::uuid();
        });
    }


    /**
     * The attributes that are mass assignable.
     */

    // protected static function newFactory(): IncidentSlaEventFactory
    // {
    //     // return IncidentSlaEventFactory::new();
    // }
}
