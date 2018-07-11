<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryComponentOpeningStockHistory extends Model
{
    protected $table = 'inventory_component_opening_stock_history';

    protected $fillable = ['inventory_component_id','opening_stock'];
}
