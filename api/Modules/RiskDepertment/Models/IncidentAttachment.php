<?php

namespace Modules\RiskDepertment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Modules\RiskDepertment\Database\Factories\IncidentAttachmentFactory;

class IncidentAttachment extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'incident_report_id','file_name','file_path','mime_type','uploaded_by'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) $model->id = (string) Str::uuid();
        });
    }

    public function incidentReport()
    {
        return $this->belongsTo(IncidentReport::class);
    }

    // protected static function newFactory(): IncidentAttachmentFactory
    // {
    //     // return IncidentAttachmentFactory::new();
    // }
}
