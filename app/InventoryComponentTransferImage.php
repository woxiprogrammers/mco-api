<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryComponentTransferImage extends Model
{
    protected $table = 'inventory_component_transfer_images';

    protected $fillable = ['name','inventory_component_transfer_id'];
}
