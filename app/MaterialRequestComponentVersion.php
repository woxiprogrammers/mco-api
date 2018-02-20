<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MaterialRequestComponentVersion extends Model
{
    protected $table = 'material_request_component_versions';

    protected $fillable = [
        'material_request_component_id','component_status_id','user_id','quantity','unit_id','remark'
    ];

    public function purchaseRequestComponentStatuses(){
        return $this->belongsTo('App\PurchaseRequestComponentStatuses','component_status_id');
    }

    public function unit(){
        return $this->belongsTo('App\Unit','unit_id');
    }

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }
}
