<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSiteChecklist extends Model
{
    protected $table = 'project_site_checklists';

    protected $fillable = ['project_site_id','title','quotation_floor_id','detail'];

    public function quotationFloor(){
        return $this->belongsTo('App\QuotationFloor','quotation_floor_id');
    }

    public function project_site(){
        return $this->belongsTo('App\ProjectSite','project_site_id','id');
    }
}
