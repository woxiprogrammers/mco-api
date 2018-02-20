<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryComponent extends Model {

    protected $table = 'inventory_components';

    protected $fillable = ['name', 'project_site_id', 'purchase_order_component_id' , 'is_material','reference_id','opening_stock'];

    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }

    public function asset(){
        return $this->belongsTo('App\Asset','reference_id');
    }

    public function material(){
        return $this->belongsTo('App\Material','reference_id');
    }

    public function fuelAssetReading(){
        return $this->hasMany('App\FuelAssetReading','inventory_component_id');
    }

    public function purchaseOrderComponent(){
        return $this->belongsTo('App\PurchaseOrderComponent','purchase_order_component_id');
    }

    public function quotation(){
        return $this->hasOne('App\Quotation','project_site_id','project_site_id');
    }

    public function inventoryComponentTransfers(){
        return $this->hasMany('App\InventoryComponentTransfers','inventory_component_id');
    }

}