<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AssetMaintenanceTransaction extends Model
{
    protected $table = 'asset_maintenance_transactions';

    protected $fillable = ['asset_maintenance_id','asset_maintenance_transaction_status_id','bill_number','grn',
        'in_time','out_time','remark','bill_amount'];

    public function assetMaintenance(){
        return $this->belongsTo('App\AssetMaintenance','asset_maintenance_id');
    }

    public function assetMaintenanceTransactionStatus(){
        return $this->belongsTo('App\AssetMaintenanceTransactionStatuses','asset_maintenance_transaction_status_id');
    }

    public function assetMaintenanceTransactionImage(){
        return $this->hasMany('App\AssetMaintenanceTransactionImages','asset_maintenance_transaction_id');
    }
}
