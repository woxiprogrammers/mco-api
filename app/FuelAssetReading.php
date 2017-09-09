<?php
    /**
     * Created by PhpStorm.
     * User: manoj
     * Date: 7/9/17
     * Time: 5:33 PM
     */

namespace App;

use Illuminate\Database\Eloquent\Model;

class FuelAssetReading extends Model {

    protected $table = 'fuel_asset_readings';

    public function inventoryComponent(){
        return $this->belongsTo('App\InventoryComponent','inventory_component_id');
    }
}