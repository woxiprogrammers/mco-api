<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderTransactionComponent extends Model
{
    protected $table = 'purchase_order_transaction_components';

    protected $fillable = ['purchase_order_component_id','purchase_order_transaction_id','quantity','unit_id'];

    public function purchaseOrderComponent(){
        return $this->belongsTo('App\PurchaseOrderComponent','purchase_order_component_id');
    }

    public function purchaseOrderTransaction(){
        return $this->belongsTo('App\PurchaseOrderTransaction','purchase_order_transaction_id');
    }

    public function unit(){
        return $this->belongsTo('App\Unit','unit_id');
    }
}
