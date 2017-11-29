<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DrawingImageVersion extends Model
{
    protected $table = 'drawing_image_versions';
    protected $fillable = ['title','name','drawing_image_id'];
}
