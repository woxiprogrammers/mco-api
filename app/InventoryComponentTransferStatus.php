<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryComponentTransferStatus extends Model
{
    protected $table = 'inventory_component_transfer_statuses';

    protected $fillable = ['name','slug'];

}
