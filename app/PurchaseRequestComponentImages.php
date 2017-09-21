<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestComponentImages extends Model
{
    protected $table = 'purchase_request_component_images';

    protected $fillable = ['name','material_request_component_id'];
}
