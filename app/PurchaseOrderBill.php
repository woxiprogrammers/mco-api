<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderBill extends Model
{
    protected $table = 'purchase_order_bills';

    protected $fillable = [
        'purchase_order_id','amount','extra_amount','cgst_percentage','cgst_amount','sgst_percentage','sgst_amount',
        'igst_percentage','igst_amount'

    ];

    public function purchaseOrder(){
        return $this->belongsTo('App\PurchaseOrder','purchase_order_id');
    }
}
