<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSite extends Model
{
    protected $table = 'project_sites';

    protected $fillable = ['name','project_id','address'];

    public function project(){
        return $this->belongsTo('App\Project','project_id','id');
    }
}
