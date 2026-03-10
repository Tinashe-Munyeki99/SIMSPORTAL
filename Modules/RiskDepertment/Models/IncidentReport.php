<?php

namespace Modules\RiskDepertment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Authentication\Models\Brand;
use Modules\Authentication\Models\Country;
use Modules\Authentication\Models\SystemUser;

// use Modules\RiskDepertment\Database\Factories\IncidentReportFactory;

class IncidentReport extends Model
{
    use HasFactory,softDeletes, Notifiable,HasApiTokens,HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */

    protected $fillable = [
        'site_id','division','location','incident_at','reported_by','accused',
        'incident_type','incident_summary','root_cause','impact',
        'immediate_action','corrective_action','preventive_action',
        'loss_still_happening','financial_loss','currency',
        'police_required','police_reported','police_station','police_case_number','police_action_plan',
        'status','severity','respondent','management_comment',
        'created_by','updated_by','country_id','brand_id',"incident_number","other_incident_type","how_incident_picked",
        "date_insurance_claim_submitted","amount_recovered","amount_unrecovered",'claim_number','submitted_by'
    ];

    public function attachments(): HasMany{
        return $this->HasMany(IncidentAttachment::class,'incident_report_id');
    }

    public function reportedBy(): HasOne
    {
        return $this->hasOne(SystemUser::class,'id',"reported_by");
    }

    public function country(): HasOne{
        return $this->hasOne(Country::class,"id","country_id");
    }

    public function brand(): HasOne{
        return $this->hasOne(Brand::class,"id","brand_id");
    }

    public function reportReviews(): HasMany{
        return $this->hasMany(ReportStatusNotes::class,'incident_report_id',"id");
    }


    // protected static function newFactory(): IncidentReportFactory
    // {
    //     // return IncidentReportFactory::new();
    // }
}
