<?php

namespace App\Http\Controllers\Inventory;

use App\InventoryComponent;
use App\InventoryComponentTransfers;
use App\InventoryTransferTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class InventoryManageController extends BaseController
{
    public function getMaterialListing(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $displayLength = 10;
            $pageId = $request->page_id;
            $totalRecords = $pageId * $displayLength;
            $inventoryComponents = InventoryComponent::where('is_material',true)->skip($totalRecords)->take($displayLength)->get()->toArray();
            $inventoryListingData = array();
            $iterator = 0;
            foreach($inventoryComponents as $key => $inventoryComponent){
                $inventoryTransferTypes = InventoryComponentTransfers::where('inventory_component_id',$inventoryComponent['id'])->pluck('transfer_type_id')->toArray();
                $inventoryListingData[$iterator]['material_name'] = $inventoryComponent['name'];
                $inventoryListingData[$iterator]['id'] = $inventoryComponent['id'];
                $inventoryListingData[$iterator]['quantity_in'] = InventoryTransferTypes::join('inventory_component_transfers','inventory_transfer_types.id','=','inventory_component_transfers.transfer_type_id')
                                                                    ->whereIn('inventory_transfer_types.id',$inventoryTransferTypes)
                                                                    ->where('inventory_transfer_types.type','IN')
                                                                    ->pluck('inventory_component_transfers.quantity')->first();
                $inventoryListingData[$iterator]['quantity_out'] = InventoryTransferTypes::join('inventory_component_transfers','inventory_transfer_types.id','=','inventory_component_transfers.transfer_type_id')
                                                                    ->whereIn('inventory_transfer_types.id',$inventoryTransferTypes)
                                                                    ->where('inventory_transfer_types.type','OUT')
                                                                    ->pluck('inventory_component_transfers.quantity')->first();
                $inventoryListingData[$iterator]['quantity_available'] = (string)($inventoryListingData[$iterator]['quantity_in'] - $inventoryListingData[$iterator]['quantity_out']);
                $iterator++;
            }

            $data['material_list'] = $inventoryListingData;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalMaterialCount = InventoryComponent::where('is_material',true)->count();
            $remainingCount = $totalMaterialCount - $totalSent;
            if($remainingCount > 0 ){
                $page_id = $pageId + 1;
                $next_url = "/inventory/listing/{$page_id}";
            }else{
                $next_url = "";
            }

        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Material Listing',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "data" => $data,
            "next_url" => $next_url,
            "message" => $message,

        ];
        return response()->json($response,$status);
    }
}
