<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryComponentTransfers extends Model{

    protected $table = 'inventory_component_transfers';

    protected $fillable = ['inventory_component_id','transfer_type_id','quantity','unit_id','remark','source_name','grn',
        'bill_number','bill_amount','vehicle_number','in_time','out_time','payment_type_id','date','next_maintenance_hour','user_id','comment_data'];

}