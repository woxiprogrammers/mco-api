<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryComponent extends Model {

    protected $table = 'inventory_components';

    public function fuelAssetReading(){
        return $this->hasMany('App\FuelAssetReading','inventory_component_id');
    }

}