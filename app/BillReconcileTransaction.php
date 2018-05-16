<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BillReconcileTransaction extends Model
{
    protected $table = 'bill_reconcile_transactions';

    protected $fillable = ['bill_id','payment_type_id','amount','transaction_slug','reference_number','remark','bank_id','paid_from_slug'];

    public function bill(){
        return $this->belongsTo('App\Bill','bill_id');
    }

    public function paymentType(){
        return $this->belongsTo('App\PaymentType','payment_type_id');
    }
}
