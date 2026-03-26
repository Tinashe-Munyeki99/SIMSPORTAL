<?php

namespace Modules\RolesAndPermissions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


// use Modules\RolesAndPermissions\Database\Factories\RoleFactory;

class Role extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function rolePermissions(){
        return $this->hasMany(RolePermission::class,'role_id','id');
    }

    // protected static function newFactory(): RoleFactory
    // {
    //     // return RoleFactory::new();
    // }
}
