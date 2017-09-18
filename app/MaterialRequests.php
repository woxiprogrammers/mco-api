<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MaterialRequests extends Model
{
    protected $table = 'material_requests';

    protected $fillable = ['project_site_id','quotation_id','user_id'];

}

