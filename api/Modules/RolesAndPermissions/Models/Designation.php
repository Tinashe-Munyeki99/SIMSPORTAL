<?php

namespace Modules\RolesAndPermissions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Modules\RolesAndPermissions\Database\Factories\DesignationFactory;

class Designation extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */

    protected $fillable = [
        'designation',
        'site_id',
        'department_id',
    ];

    // protected static function newFactory(): DesignationFactory
    // {
    //     // return DesignationFactory::new();
    // }
}
