<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AssetMaintenanceImage extends Model
{
    protected $table = 'asset_maintenance_images';

    protected $fillable = ['name','asset_maintenance_id'];
}
