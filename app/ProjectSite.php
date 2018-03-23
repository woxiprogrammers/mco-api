<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSite extends Model
{
    protected $table = 'project_sites';

    protected $fillable = ['name','project_id','address','city_id','advanced_amount','advanced_balance','distributed_salary_amount','distributed_purchase_peticash_amount'];

    public function project(){
        return $this->belongsTo('App\Project','project_id','id');
    }
}
