<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderRequestComponentImage extends Model
{
    protected $table = 'purchase_order_request_component_images';

    protected $fillable = ['purchase_order_request_component_id','name','caption','is_vendor_approval'];

    public function purchaseOrderRequestComponent(){
        return $this->belongsTo('App\PurchaseOrderRequestComponents','purchase_order_request_component_id');
    }
}
