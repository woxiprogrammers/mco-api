<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MaterialRequestComponentVersion extends Model
{
    protected $table = 'material_request_component_versions';

    protected $fillable = [
        'material_request_component_id','component_status_id','user_id','quantity','unit_id','remark'
    ];
}
