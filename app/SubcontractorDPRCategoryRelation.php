<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SubcontractorDPRCategoryRelation extends Model
{
    protected $table = 'subcontractor_dpr_category_relations';

    protected $fillable = ['subcontractor_id','dpr_main_category_id'];

    public function subcontractor(){
        return $this->belongsTo('App\Subcontractor','subcontractor_id');
    }

    public function dprMainCategory(){
        return $this->belongsTo('App\DprMainCategory','dpr_main_category_id');
    }
}
