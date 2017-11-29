<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DrawingImageComment extends Model
{
    protected $table = 'drawing_image_comments';
    protected $fillable = ['drawing_image_version_id','comment'];
}
