<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Modules\Site\Database\Factories\SiteFactory;

class Site extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */


    protected $fillable = [
        'site_name',
        'domain',
        'host_ip',
        'host_port',
        ];

    // protected static function newFactory(): SiteFactory
    // {
    //     // return SiteFactory::new();
    // }
}
