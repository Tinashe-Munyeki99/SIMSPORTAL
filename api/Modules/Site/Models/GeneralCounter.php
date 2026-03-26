<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Modules\Site\Database\Factories\GeneralCounterFactory;

class GeneralCounter extends Model
{
    use HasFactory, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): GeneralCounterFactory
    // {
    //     // return GeneralCounterFactory::new();
    // }
}
