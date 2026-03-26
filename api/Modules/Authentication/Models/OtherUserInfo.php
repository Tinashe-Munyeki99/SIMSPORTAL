<?php

namespace Modules\Authentication\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Modules\Authentication\Database\Factories\OtherUserInfoFactory;

class OtherUserInfo extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "user_id",
        "phone",
        "position",
        "city",
        "country",
        "shop_name"
    ];

    public function country():belongsTo{
        return $this->belongsTo(Country::class,"country","id");
    }

    // protected static function newFactory(): OtherUserInfoFactory
    // {
    //     // return OtherUserInfoFactory::new();
    // }

    public function brand():belongsTo
    {
        return $this->belongsTo(Brand::class,"brand_id","id");
    }
}
