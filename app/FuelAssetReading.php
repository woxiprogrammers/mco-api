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

    protected $fillable = ['inventory_component_id','start_reading','stop_reading','start_time','stop_time','top_up_time','top_up','electricity_per_unit','fuel_per_unit'];

    public function inventoryComponent(){
        return $this->belongsTo('App\InventoryComponent','inventory_component_id');
    }
}