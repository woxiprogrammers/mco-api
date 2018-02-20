<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderTransactionImage extends Model
{
    protected $table = 'purchase_order_transaction_images';

    protected $fillable = ['name','purchase_order_transaction_id','is_pre_grn'];

    public function purchaseOrderTransaction(){
        return $this->belongsTo('App\PurchaseOrderTransaction','purchase_order_transaction_id');
    }
}
