<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderAdvancePayment extends Model
{
    protected $table = 'purchase_order_advance_payments';

    protected $fillable = ['purchase_order_id','payment_id','amount','reference_number','bank_id','paid_from_slug'];

    public function purchaseOrder(){
        return $this->belongsTo('App\PurchaseOrder','purchase_order_id');
    }

    public function paymentType(){
        return $this->belongsTo('App\PaymentType','payment_id');
    }
}
