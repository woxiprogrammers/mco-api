<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SubcontractorBillReconcileTransaction extends Model
{
    protected $table = 'subcontractor_bill_reconcile_transactions';

    protected $fillable = ['subcontractor_bill_id','payment_type_id','amount','transaction_slug','reference_number','remark','bank_id','paid_from_slug'];

    public function subcontractorBill(){
        return $this->belongsTo('App\SubcontractorBill','subcontractor_bill_id');
    }

    public function paymentType(){
        return $this->belongsTo('App\PaymentType','payment_type_id');
    }
}
