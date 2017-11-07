<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderComponentImage extends Model
{
    protected $table = 'purchase_order_component_images';

    protected $fillable = ['name','purchase_order_component_id','caption','is_vendor_approval'];

    public function purchaseOrderComponent(){
        return $this->belongsTo('App\PurchaseOrderComponent','purchase_order_component_id');
    }
}
