<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestComponents extends Model
{
    protected $table = 'purchase_request_components';

    protected $fillable = ['purchase_request_id','material_request_component_id'];
}
