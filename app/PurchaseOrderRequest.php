<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderRequest extends Model
{
    protected $table = 'purchase_order_requests';

    protected $fillable = ['purchase_request_id','user_id'];

    public function purchaseRequest(){
        return $this->belongsTo('App\PurchaseRequests','purchase_request_id');
    }

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }

    public function purchaseOrderRequestComponents(){
        return $this->hasMany('App\PurchaseOrderRequestComponent','purchase_order_request_id');
    }
}

