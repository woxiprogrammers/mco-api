<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MaterialRequestComponents extends Model
{
    protected $table = 'material_request_components';

    protected $fillable = ['material_request_id','name','quantity','unit_id','component_type_id','component_status_id','serial_no'];

    public function unit(){
        return $this->belongsTo('App\Unit','unit_id');
    }

    public function materialRequestComponentTypes(){
        return $this->belongsTo('App\MaterialRequestComponentTypes','component_type_id');
    }

    public function purchaseRequestComponentStatuses(){
        return $this->belongsTo('App\PurchaseRequestComponentStatuses','component_status_id');
    }

    public function materialRequestComponentImages(){
        return $this->hasMany('App\MaterialRequestComponentImages','material_request_component_id');
    }

    public function materialRequest(){
        return $this->belongsTo('App\MaterialRequests','material_request_id');
    }
}
