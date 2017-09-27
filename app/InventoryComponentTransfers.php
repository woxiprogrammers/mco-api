<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryComponentTransfers extends Model{

    protected $table = 'inventory_component_transfers';

    protected $fillable = ['inventory_component_id','transfer_type_id','quantity','next_maintenance_hour','user_id','comment_data'];

}