<?php

namespace App\Http\Controllers\Inventory;
use App\Asset;
use App\FuelAssetReading;
use App\InventoryComponent;
use App\InventoryComponentTransfers;
use App\InventoryTransferTypes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;


class AssetManagementController extends BaseController
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function getAssetListing(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $pageId = $request->page;
            $projectSiteId = $request->project_site_id;
            $inventoryComponents = InventoryComponent::where('is_material',((boolean)false))->where('project_site_id',$projectSiteId)->get()->toArray();
            $inventoryListingData = array();
            $iterator = 0;
            $inTransferIds = InventoryTransferTypes::where('type','ilike','in')->pluck('id')->toArray();
            $outTransferIds = InventoryTransferTypes::where('type','ilike','out')->pluck('id')->toArray();
            foreach($inventoryComponents as $key => $inventoryComponent){
                $lastOutTransfer = InventoryComponentTransfers::where('inventory_component_id', $inventoryComponent['id'])->whereIn('transfer_type_id',$outTransferIds)->orderBy('created_at','desc')->first();
                $currentInComponent = InventoryComponentTransfers::where('inventory_component_id', $inventoryComponent['id'])->whereIn('transfer_type_id',$inTransferIds)->where('created_at','>',$lastOutTransfer['created_at'])->first();
                if($currentInComponent != null){
                    $inventoryListingData[$iterator]['assets_name'] = $inventoryComponent['name'];
                    $inventoryListingData[$iterator]['id'] = $inventoryComponent['id'];
                    if($inventoryComponent['reference_id'] == null || $inventoryComponent['reference_id'] == ''){
                        $inventoryListingData[$iterator]['model_number'] = '-';
                        $inventoryListingData[$iterator]['assets_units'] = '-';
                        $inventoryListingData[$iterator]['total_work_hour'] = '-';
                        $inventoryListingData[$iterator]['total_diesel_consume'] = '-';
                        $inventoryListingData[$iterator]['is_diesel'] = false;
                    }else{
                        $asset = Asset::where('id',$inventoryComponent['reference_id'])->first();
                        $inventoryListingData[$iterator]['model_number'] = $asset->model_number;
                        if($asset['is_fuel_dependent'] == true){
                            $readingInfo = FuelAssetReading::where('inventory_component_id',$inventoryComponent['id'])->get();
                            $assetUnits = 0;
                            $totalWorkHour = 0;
                            $totalDieselConsume = 0;
                            foreach ($readingInfo as $reading){
                                $assetUnits += (((int)$reading['stop_reading']) - ((int)$reading['start_reading']));
                                $startTime = Carbon::parse($reading['start_time']);
                                $endTime = Carbon::parse($reading['stop_time']);
                                $totalWorkHour += $endTime->diffInHours($startTime);
                                $totalDieselConsume += ($asset['litre_per_unit'] * ((((int)$reading['stop_reading']) - ((int)$reading['start_reading']))));
                            }
                            $inventoryListingData[$iterator]['assets_units'] = $assetUnits;
                            $inventoryListingData[$iterator]['total_work_hour'] = $totalWorkHour;
                            $inventoryListingData[$iterator]['total_diesel_consume'] = $totalDieselConsume;
                            $inventoryListingData[$iterator]['is_diesel'] = true;
                        }else{
                            $inventoryListingData[$iterator]['assets_units'] = '-';
                            $inventoryListingData[$iterator]['total_work_hour'] = '-';
                            $inventoryListingData[$iterator]['total_diesel_consume'] = '-';
                            $inventoryListingData[$iterator]['is_diesel'] = false;
                        }
                    }
                    $iterator++;
                }
            }
            $data['assets_list'] = array();
            $displayLength = 10;
            $start = ((int)$pageId) * $displayLength;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalMaterialCount = count($inventoryListingData);
            $remainingCount = $totalMaterialCount - $totalSent;
            for($iterator = $start,$jIterator = 0; $iterator < $totalSent && $jIterator < $totalMaterialCount; $iterator++,$jIterator++){
                $data['assets_list'][] = $inventoryListingData[$iterator];
            }
            if($remainingCount > 0 ){
                $page_id = $pageId + 1;
                $next_url = "/inventory/asset/listing";
            }else{
                $next_url = "";
                $page_id = "";
            }

        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Asset Listing',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            $next_url = "";
            $page_id = "";
            Log::critical(json_encode($data));
        }
        $response = [
            "data" => $data,
            "next_url" => $next_url,
            "page_id" => $page_id,
            "message" => $message,

        ];
        return response()->json($response,$status);
    }

    public function getSummaryAssetListing(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $inventoryComponent = InventoryComponent::where('id',$request->inventory_component_id)->first();

            $fuelAssetReadings = $inventoryComponent->fuelAssetReading;
            $asset = Asset::where('id',$inventoryComponent['reference_id'])->first();
            $summaryAssetListing = array();
            $iterator = 0;
            foreach($fuelAssetReadings as $key => $assetReading){
                $inventoryTransferTypes = InventoryComponentTransfers::where('inventory_component_id',$assetReading->inventory_component_id)->pluck('transfer_type_id')->toArray();
                $summaryAssetListing[$iterator]['id'] = $assetReading['id'];
                $summaryAssetListing[$iterator]['assets_units'] = (((int)$assetReading['stop_reading']) - ((int)$assetReading['start_reading']));
                $summaryAssetListing[$iterator]['work_hour_in_day'] = Carbon::parse($assetReading['stop_time'])->diffInHours(Carbon::parse($assetReading['start_time']));
                $summaryAssetListing[$iterator]['total_diesel_consume'] = ($asset['litre_per_unit'] * ((((int)$assetReading['stop_reading']) - ((int)$assetReading['start_reading']))));
                $summaryAssetListing[$iterator]['start_time'] = $assetReading['start_time'];
                $summaryAssetListing[$iterator]['stop_time'] = $assetReading['stop_time'];
                $summaryAssetListing[$iterator]['top_up_time'] = $assetReading['top_up_time'];
                $inventory_quantity_in = InventoryTransferTypes::join('inventory_component_transfers','inventory_transfer_types.id','=','inventory_component_transfers.transfer_type_id')
                                            ->whereIn('inventory_transfer_types.id',$inventoryTransferTypes)
                                            ->where('inventory_transfer_types.type','IN')
                                            ->sum('inventory_component_transfers.quantity');

                $inventory_quantity_out = InventoryTransferTypes::join('inventory_component_transfers','inventory_transfer_types.id','=','inventory_component_transfers.transfer_type_id')
                                            ->whereIn('inventory_transfer_types.id',$inventoryTransferTypes)
                                            ->where('inventory_transfer_types.type','OUT')
                                            ->sum('inventory_component_transfers.quantity');
                
                $summaryAssetListing[$iterator]['fuel_remaining'] = ($inventory_quantity_in - $inventory_quantity_out - $summaryAssetListing[$iterator]['total_diesel_consume']);
                $iterator++;
            }
            $data['assets_summary_data']['assets_summary_list'] = $summaryAssetListing;
            $data['asset_name'] = $asset['name'];
            $data['next_url'] = "";
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Summary Asset Listing',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "data" => $data,
            "message" => $message
        ];
        return response()->json($response,$status);
    }
}