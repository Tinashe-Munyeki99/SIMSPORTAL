<?php

namespace Modules\RiskDepertment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Authentication\Models\SystemUser;

// use Modules\RiskDepertment\Database\Factories\ReportStatusNotesFactory;

class ReportStatusNotes extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function user(){
        return $this->belongsTo(SystemUser::class,'user_id','id');

    }

    // protected static function newFactory(): ReportStatusNotesFactory
    // {
    //     // return ReportStatusNotesFactory::new();
    // }
}
