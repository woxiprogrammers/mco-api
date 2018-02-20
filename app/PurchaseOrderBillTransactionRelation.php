<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderBillTransactionRelation extends Model
{
    protected $table = 'purchase_order_bill_transaction_relation';

    protected $fillable = ['purchase_order_transaction_id','purchase_order_bill_id'];

    public function purchaseOrderTransaction(){
        return $this->belongsTo('App\PurchaseOrderTransaction','purchase_order_transaction_id');
    }

    public function purchaseOrderBill(){
        return $this->belongsTo('App\PurchaseOrderBill','purchase_order_bill_id');
    }
}
