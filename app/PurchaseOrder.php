<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = ['user_id','vendor_id','is_approved','purchase_request_id'];

    public function purchaseRequest(){
        return $this->belongsTo('App\PurchaseRequests' , 'purchase_request_id');
    }

    public function purchaseOrderComponent(){
        return $this->hasMany('App\PurchaseOrderComponent','purchase_order_id');
    }

    public function vendor(){
        return $this->belongsTo('App\Vendor' ,'vendor_id');
    }

}
