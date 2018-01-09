<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subcontractor extends Model
{
    protected $table = 'subcontractor';

    protected $fillable = ['company_name','category','subcategory','desc_prod_service','nature_of_work','sc_turnover_pre_yr',
        'sc_turnover_two_fy_ago','primary_cont_person_name','primary_cont_person_mob_number',
        'primary_cont_person_email','escalation_cont_person_name','escalation_cont_person_mob_number',
        'sc_pancard_no','sc_service_no','sc_vat_no',
        'is_active','created_at','updated_at','subcontractor_name'
    ];

    public function dprCategoryRelations(){
        return $this->hasMany('App\SubcontractorDPRCategoryRelation','subcontractor_id');
    }
}
