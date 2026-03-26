<?php

namespace Modules\RolesAndPermissions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Modules\RolesAndPermissions\Database\Factories\DepertmentFactory;

class Depertment extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "name",
        "site_id"
    ];

    // protected static function newFactory(): DepertmentFactory
    // {
    //     // return DepertmentFactory::new();
    // }
}
