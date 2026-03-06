<?php

namespace Modules\RiskDepertment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Modules\RiskDepertment\Database\Factories\IncidentNotificationReceipientFactory;

class IncidentNotificationReceipient extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'rule_id',
        'email',
    ];


    public function rule(){
        return $this->belongsTo(IncidentNotificationRule::class, 'rule_id');
    }

    // protected static function newFactory(): IncidentNotificationReceipientFactory
    // {
    //     // return IncidentNotificationReceipientFactory::new();
    // }
}
