<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryComponentTransfers extends Model
{

    protected $table = 'inventory_component_transfers';

    protected $fillable = [
        'inventory_component_id', 'transfer_type_id', 'quantity', 'unit_id', 'remark', 'source_name', 'grn',
        'bill_number', 'bill_amount', 'vehicle_number', 'in_time', 'out_time', 'payment_type_id', 'date', 'next_maintenance_hour',
        'user_id', 'comment_data', 'inventory_component_transfer_status_id', 'rate_per_unit', 'cgst_percentage',
        'sgst_percentage', 'igst_percentage', 'cgst_amount', 'sgst_amount', 'igst_amount', 'total', 'vendor_id', 'transportation_amount',
        'transportation_cgst_percent', 'transportation_sgst_percent', 'transportation_igst_percent', 'driver_name', 'mobile', 'related_transfer_id', 'inventory_transfer_challan_id'
    ];

    public function inventoryComponent()
    {
        return $this->belongsTo('App\InventoryComponent', 'inventory_component_id');
    }
    public function unit()
    {
        return $this->belongsTo('App\Unit', 'unit_id');
    }
    public function transferType()
    {
        return $this->belongsTo('App\InventoryTransferTypes', 'transfer_type_id');
    }
    public function payment()
    {
        return $this->belongsTo('App\PaymentType', 'payment_type_id');
    }
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }
    public function images()
    {
        return $this->hasMany('App\InventoryComponentTransferImage', 'inventory_component_transfer_id');
    }
    public function inventoryComponentTransferStatus()
    {
        return $this->belongsTo('App\InventoryComponentTransferStatus', 'inventory_component_transfer_status_id');
    }
    public function vendor()
    {
        return $this->belongsTo('App\Vendor', 'vendor_id');
    }
}
