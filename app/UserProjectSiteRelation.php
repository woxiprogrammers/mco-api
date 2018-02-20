<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserProjectSiteRelation extends Model
{
    protected $table = 'user_project_site_relation';

    protected $fillable = ['user_id','project_site_id'];

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }

    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }
}
