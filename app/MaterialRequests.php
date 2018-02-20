<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MaterialRequests extends Model
{
    protected $table = 'material_requests';

    protected $fillable = ['project_site_id','quotation_id','user_id','assigned_to','serial_no','format_id','on_behalf_of'];

    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }

    public function materialRequestComponents(){
        return $this->hasMany('App\MaterialRequestComponents','material_request_id');
    }
}

