<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\CustomTraits\InventoryTrait;
use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\Http\Controllers\CustomTraits\UnitTrait;
use App\InventoryComponent;
use App\InventoryComponentTransfers;
use App\InventoryComponentTransferStatus;
use App\InventoryTransferChallan;
use App\InventoryTransferTypes;
use App\Material;
use App\MaterialVersion;
use App\Module;
use App\ProductMaterialRelation;
use App\Quotation;
use App\QuotationMaterial;
use App\QuotationProduct;
use App\Unit;
use App\UnitConversion;
use App\UserLastLogin;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class InventoryTransferChallanController extends BaseController
{
    use InventoryTrait;
    use UnitTrait;
    use NotificationTrait;
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if (!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function getPendingChallans($projectSiteId)
    {
        try {
            $approvedChallans = InventoryTransferChallan::join('inventory_component_transfers', 'inventory_component_transfers.inventory_transfer_challan_id', '=', 'inventory_transfer_challan.id')
                ->join('inventory_component_transfer_statuses', 'inventory_transfer_challan.inventory_component_transfer_status_id', '=', 'inventory_component_transfer_statuses.id')
                ->where('inventory_component_transfer_statuses.slug', 'open')
                ->whereNull('project_site_in_date')->where('project_site_in_id', $projectSiteId)
                ->select('inventory_transfer_challan.id', 'inventory_transfer_challan.challan_number')
                ->get()->toArray();
            $status = 200;
            $message = "Success";
        } catch (Exception $e) {
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Challan List',
                'params' => [],
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $approvedChallans = [];
        }
        $response = [
            "data"      => $approvedChallans,
            "message"   => $message,
        ];
        return response()->json($response, $status);
    }

    public function getChallanDetail($challanId)
    {
        try {
            $challan = InventoryTransferChallan::find($challanId);
            $outTransferType = InventoryTransferTypes::where('slug', 'site')->where('type', 'OUT')->first();
            $inventoryComponentOutTransfers = $outTransferType->inventoryComponentTransfers->where('inventory_transfer_challan_id', $challan['id']);
            foreach ($inventoryComponentOutTransfers as $outTransferComponent) {
                $siteInQuantity = '-';
                if ($outTransferComponent['related_transfer_id'] != null) {
                    $inTransferComponent = InventoryComponentTransfers::find($outTransferComponent['related_transfer_id']);
                    $siteInQuantity = $inTransferComponent->quantity ?? '-';
                }
                $components[] = [
                    'name'              => $outTransferComponent->inventoryComponent->name,
                    'is_material'       => $outTransferComponent->inventoryComponent->is_material,
                    'unit'              => $outTransferComponent->unit->name,
                    'site_out_quantity' => $outTransferComponent->quantity,
                    'site_in_quantity'  => $siteInQuantity
                ];
            }
            $challan['inventory_component_transfer_status_name'] = $challan->inventoryComponentTransferStatus->name;
            $challan['items'] = $components;
            $challan['other_data'] = $challan->otherData()->toArray();
            $status = 200;
            $message = "Success";
        } catch (Exception $e) {
            dd($e->getMessage());
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Challan Detail',
                'params' => [],
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $challan = [];
        }
        $response = [
            "data"      => $challan,
            "message"   => $message,
        ];
        return response()->json($response, $status);
    }
}
