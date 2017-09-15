<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryComponent extends Model {

    protected $table = 'inventory_components';

    public function fuelAssetReading(){
        return $this->hasMany('App\FuelAssetReading','inventory_component_id');
    }

    public function quotation(){
        return $this->hasOne('App\Quotation','project_site_id','project_site_id');
    }

}