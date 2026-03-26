<?php

namespace Modules\RiskDepertment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Modules\RiskDepertment\Database\Factories\IncidentNotificationRuleFactory;

class IncidentNotificationRule extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'currency',
        'min_amount',
        'max_amount',
        'is_active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(IncidentNotificationReceipient::class, 'rule_id');
    }

    // protected static function newFactory(): IncidentNotificationRuleFactory
    // {
    //     // return IncidentNotificationRuleFactory::new();
    // }
}
