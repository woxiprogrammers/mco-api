<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderComponent extends Model
{
    protected $table = 'purchase_order_components';

    protected $fillable = ['purchase_order_id','purchase_request_component_id','rate_per_unit','gst','hsn_code','expected_delivery_date','remark','credited_days'];

    public function purchaseRequestComponent(){
        return $this->belongsTo('App\PurchaseRequestComponents' , 'purchase_request_component_id');
    }

    public function purchaseOrder(){
        return $this->belongsTo('App\PurchaseOrder','purchase_order_id');
    }
}
