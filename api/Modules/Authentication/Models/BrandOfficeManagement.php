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

// use Modules\Authentication\Database\Factories\BrandOfficeManagementFactory;

class BrandOfficeManagement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';


protected $fillable = ["user_id","brand_id","office_id","country_id"];

    // protected static function newFactory(): BrandOfficeManagementFactory
    // {
    //     // return BrandOfficeManagementFactory::new();
    // }

    public function brand():belongsTo{
        return $this->belongsTo(Brand::class,"brand_id","id");
    }

    public function office():belongsTo{
        return $this->belongsTo(Office::class,"office_id","id");
    }
}
