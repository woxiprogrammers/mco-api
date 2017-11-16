<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderBill extends Model
{
    protected $table = 'purchase_order_bills';

    protected $fillable = [
        'purchase_order_component_id','bill_number','vehicle_number','grn','in_time','out_time','quantity',
        'unit_id','bill_amount','remark','purchase_order_bill_status_id'
    ];

    public function purchaseOrderComponent(){
        return $this->belongsTo('App\PurchaseOrderComponent','purchase_order_component_id');
    }

    public function unit(){
        return $this->belongsTo('App\Unit','unit_id');
    }

    public function purchaseOrderBillStatuses(){
        return $this->belongsTo('App\PurchaseOrderBillStatus','purchase_order_bill_status_id');
    }
}
