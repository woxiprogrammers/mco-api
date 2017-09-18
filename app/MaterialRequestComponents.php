<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MaterialRequestComponents extends Model
{
    protected $table = 'material_request_components';

    protected $fillable = ['material_request_id','name','quantity','unit_id','component_type_id','component_status_id'];
}
