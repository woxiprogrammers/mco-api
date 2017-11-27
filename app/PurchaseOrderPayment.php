<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderPayment extends Model
{
    protected $table = 'purchase_order_payments';

    protected $fillable = ['purchase_order_bill_id','payment_id','amount','reference_number'];

    public function purchaseOrderBill(){
        return $this->belongsTo('App\PurchaseOrderBill','purchase_order_bill_id');
    }

    public function paymentType(){
        return $this->belongsTo('App\PaymentType','payment_id');
    }
}
