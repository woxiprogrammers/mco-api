<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MaterialImages extends Model
{
    protected $table = 'material_images';
    protected $fillable = ['name','material_id'];
}
