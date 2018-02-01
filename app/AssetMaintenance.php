<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AssetMaintenance extends Model
{
    protected $table = 'asset_maintenance';

    protected $fillable = ['asset_id','project_site_id', 'asset_maintenance_status_id', 'user_id', 'remark'];

    public function asset(){
        return $this->belongsTo('App\Asset','asset_id');
    }

    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }

    public function assetMaintenanceStatus(){
        return $this->belongsTo('App\AssetMaintenanceStatus','asset_maintenance_status_id');
    }

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }

    public function assetMaintenanceImage(){
        return $this->hasMany('App\AssetMaintenanceImage','asset_maintenance_id');
    }

    public function assetMaintenanceVendorRelation(){
        return $this->hasMany('App\AssetMaintenanceVendorRelation','asset_maintenance_id');
    }

    public function assetMaintenanceTransaction(){
        return $this->hasMany('App\AssetMaintenanceTransaction','asset_maintenance_id');
    }

}
