<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AssetMaintenanceTransactionImages extends Model
{
    protected $table = 'asset_maintenance_transaction_images';

    protected $fillable = ['asset_maintenance_transaction_id','name','is_pre_grn'];

    public function assetMaintenanceTransaction(){
        return $this->belongsTo('App\AssetMaintenanceTransaction','asset_maintenance_transaction_id');
    }

}
