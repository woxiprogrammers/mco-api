<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AssetImages extends Model
{
    protected $table = 'asset_images';
    protected $fillable = ['name','asset_id'];
}
