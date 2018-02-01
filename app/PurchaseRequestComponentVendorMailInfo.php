<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestComponentVendorMailInfo extends Model
{
    protected $table = 'purchase_request_component_vendor_mail_info';

    protected $fillable = ['user_id','created_at','updated_at','type_slug','vendor_id','reference_id','is_client','client_id'];

    public function vendor(){
        return $this->belongsTo('App\Vendor','vendor_id');
    }

    public function purchaseRequest(){
        return $this->belongsTo('App\PurchaseRequest','reference_id');
    }

    public function purchaseOrder(){
        return $this->belongsTo('App\PurchaseOrder','reference_id');
    }

    public function client(){
        return $this->belongsTo('App\Client','client_id');
    }
}
