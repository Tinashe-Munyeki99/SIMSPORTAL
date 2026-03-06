<?php

namespace Modules\RolesAndPermissions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Modules\RolesAndPermissions\Database\Factories\RolePermissionFactory;

class RolePermission extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['id','role_id','permission_id','site_id'];

    // protected static function newFactory(): RolePermissionFactory
    // {
    //     // return RolePermissionFactory::new();
    // }

    public function role(): BelongsTo{
        return $this->belongsTo(Role::class,'role_id','id');
    }
    public function permission(): BelongsTo{
        return $this->belongsTo(Permission::class,'permission_id','id');
    }
}
