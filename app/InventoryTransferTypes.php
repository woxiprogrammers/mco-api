<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryTransferTypes extends Model
{

    protected $table = 'inventory_transfer_types';

    public function inventoryComponentTransfers()
    {
        return $this->hasMany(InventoryComponentTransfers::class, 'transfer_type_id');
    }
}
