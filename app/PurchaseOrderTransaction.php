<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderTransaction extends Model
{
    protected $table = 'purchase_order_transactions';

    protected $fillable = ['purchase_order_id','purchase_order_transaction_status_id','bill_number','vehicle_number','grn','in_time','out_time','remark'];

    public function purchaseOrder(){
        return $this->belongsTo('App\PurchaseOrder','purchase_order_id');
    }

    public function purchaseOrderTransactionStatus(){
        return $this->belongsTo('App\PurchaseOrderTransactionStatus','purchase_order_transaction_status_id');
    }
}
