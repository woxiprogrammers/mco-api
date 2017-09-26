<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MaterialRequestComponentImages extends Model
{
    protected $table = 'material_request_component_images';

    protected $fillable = ['name','material_request_component_id'];
}
