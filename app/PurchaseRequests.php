<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequests extends Model
{
    protected $table = 'purchase_requests';

    protected $fillable = ['quotation_id','project_site_id','user_id','behalf_of_user_id','purchase_component_status_id'];

    public function purchaseRequestComponentStatuses(){
        return $this->belongsTo('App\PurchaseRequestComponentStatuses','purchase_component_status_id');
    }

    public function purchaseRequestComponents(){
        return $this->hasMany('App\PurchaseRequestComponents','purchase_request_id');
    }
}
