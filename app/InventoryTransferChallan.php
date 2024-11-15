<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InventoryTransferChallan extends Model
{
    protected $table = 'inventory_transfer_challan';

    protected $fillable = [
        'challan_number', 'project_site_out_id', 'project_site_in_id', 'project_site_out_date', 'project_site_in_date', 'inventory_component_transfer_status_id'
    ];

    public function projectSiteOut()
    {
        return $this->belongsTo('App\ProjectSite', 'project_site_out_id');
    }

    public function projectSiteIn()
    {
        return $this->belongsTo('App\ProjectSite', 'project_site_in_id');
    }

    public function inventoryComponentTransferStatus()
    {
        return $this->belongsTo('App\InventoryComponentTransferStatus', 'inventory_component_transfer_status_id');
    }

    public function otherData()
    {
        $outTransferTypeId = InventoryTransferTypes::where('slug', 'site')->where('type', 'OUT')->pluck('id')->first();
        $data = InventoryComponentTransfers::where('inventory_transfer_challan_id', $this['id'])->where('transfer_type_id', $outTransferTypeId)
            ->select(
                'bill_number',
                'date',
                'vendor_id',
                'transportation_amount',
                'transportation_cgst_percent',
                'transportation_sgst_percent',
                'transportation_igst_percent',
                'driver_name',
                'mobile',
                'vehicle_number',
                'source_name'
            )->first();
        $data['vendor_name'] = Vendor::where('id', $data['vendor_id'])->pluck('name')->first();
        $data['transportation_total'] = $data['transportation_tax_total'] = 0;
        if ($data['transportation_amount'] != null && $data['transportation_amount'] != "0") {
            $transportation_amount = $data['transportation_amount'];
            $cgstAmount = $transportation_amount * ($data['transportation_cgst_percent'] / 100) ?? 0;
            $sgstAmount = $transportation_amount * ($data['transportation_sgst_percent'] / 100) ?? 0;
            $igstAmount = $transportation_amount * ($data['transportation_igst_percent'] / 100) ?? 0;
            $data['transportation_tax_total'] = $cgstAmount + $sgstAmount + $igstAmount;
            $data['transportation_total'] = $transportation_amount + $cgstAmount + $sgstAmount + $igstAmount;
        } else {
            $data['transportation_amount'] = $data['transportation_cgst_percent'] = $data['transportation_sgst_percent'] = $data['transportation_igst_percent'] = 0;
        }
        $data['transportation_cgst_percent'] = (float) $data['transportation_cgst_percent'];
        if ($data) {
            $data = $this->getcastedData($data);
        }
        return $data;
    }

    public function getcastedData($data)
    {
        $data['bill_number'] = (string) ($data['bill_number'] ?? '');
        $data['transportation_amount'] = (float) ($data['transportation_amount'] ?? 0.0);
        $data['transportation_cgst_percent'] = (float) ($data['transportation_cgst_percent'] ?? 0.0);
        $data['transportation_sgst_percent'] = (float) ($data['transportation_sgst_percent'] ?? 0.0);
        $data['transportation_igst_percent'] = (float) ($data['transportation_igst_percent'] ?? 0.0);
        $data['driver_name'] = (string) ($data['driver_name'] ?? '');
        $data['mobile'] = (string) ($data['mobile'] ?? '');
        $data['source_name'] = (string) ($data['source_name'] ?? '');
        $data['vendor_name'] = (string) ($data['vendor_name'] ?? '');
        return $data;
    }
}
