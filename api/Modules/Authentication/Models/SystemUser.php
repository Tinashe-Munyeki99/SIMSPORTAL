<?php

namespace Modules\Authentication\Models;


use Beta\Microsoft\Graph\SecurityNamespace\Model\Department;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\RolesAndPermissions\Models\Depertment;
use Modules\RolesAndPermissions\Models\Designation;
use Modules\RolesAndPermissions\Models\Role;

// use Modules\Authentication\Database\Factories\SystemUserFactory;

class SystemUser extends Authenticatable
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function otherInfo():hasOne
    {
        return $this->hasOne(OtherUserInfo::class,"user_id","id");
    }

    public function designation():belongsTo
    {
        return $this->belongsTo(Designation::class,"designation_id","id");
    }

    public function department():belongsTo
    {
        return $this->belongsTo(Depertment::class,"department_id","id");
    }

    public function role():belongsTo
    {
        return $this->belongsTo(Role::class,"role_id","id");
    }
    // protected static function newFactory(): SystemUserFactory
    // {
    //     // return SystemUserFactory::new();
    // }

    public function brandOffice():hasMany{
        return $this->hasMany(BrandOfficeManagement::class,"user_id","id");
    }
}
