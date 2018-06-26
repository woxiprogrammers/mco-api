<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DprImage extends Model
{
    protected $table = 'dpr_images';

    protected $fillable = ['name'];
}
