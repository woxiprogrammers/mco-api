<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AssetMaintenanceVendorRelation extends Model
{
    protected $table = 'asset_maintenance_vendor_relation';

    protected $fillable = ['asset_maintenance_id','vendor_id','quotation_amount','user_id','is_approved'];

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }

    public function assetMaintenance(){
        return $this->belongsTo('App\AssetMaintenance','asset_maintenance_id');
    }

    public function vendor(){
        return $this->belongsTo('App\Vendor','vendor_id');
    }
}
