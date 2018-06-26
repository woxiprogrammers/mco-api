<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderRequestComponent extends Model
{
    protected $table = 'purchase_order_request_components';

    protected $fillable = ['purchase_order_request_id','is_approved','purchase_request_component_vendor_relation_id',
        'rate_per_unit','gst','hsn_code','expected_delivery_date','remark','credited_days',
        'quantity','unit_id','cgst_percentage','sgst_percentage','igst_percentage','cgst_amount',
        'sgst_amount','igst_amount','total','category_id','approve_disapprove_by_user'
    ];

    public function purchaseOrderRequest(){
        return $this->belongsTo('App\PurchaseOrderRequest','purchase_order_request_id');
    }

    public function purchaseRequestComponentVendorRelation(){
        return $this->belongsTo('App\PurchaseRequestComponentVendorRelation','purchase_request_component_vendor_relation_id');
    }

    public function purchaseOrderRequestComponentImages(){
        return $this->hasMany('App\PurchaseOrderRequestComponentImage','purchase_order_request_component_id');
    }

    public function unit(){
        return $this->belongsTo('App\Unit','unit_id');
    }

    public function user(){
        return $this->belongsTo('App\User','approve_disapprove_by_user');
    }
}
