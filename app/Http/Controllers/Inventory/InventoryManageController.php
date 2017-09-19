<?php

namespace App\Http\Controllers\Inventory;

use App\InventoryComponent;
use App\InventoryComponentTransfers;
use App\InventoryTransferTypes;
use App\MaterialVersion;
use App\ProductMaterialRelation;
use App\Quotation;
use App\QuotationMaterial;
use App\QuotationProduct;
use App\Unit;
use App\UnitConversion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class InventoryManageController extends BaseController
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function getMaterialListing(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $displayLength = 10;
            $pageId = $request->page_id;
            $totalRecords = $pageId * $displayLength;
            $inventoryComponents = InventoryComponent::where('project_site_id',$request->project_site_id)->where('is_material',true)->skip($totalRecords)->take($displayLength)->get();
            $quotation = Quotation::where('project_site_id',$request->project_site_id)->first();
            $inventoryListingData = array();
            $iterator = 0;
            foreach($inventoryComponents as $key => $inventoryComponent){
                $inventoryTransferTypes = InventoryComponentTransfers::where('inventory_component_id',$inventoryComponent['id'])->pluck('transfer_type_id')->toArray();
                $isQuotationMaterial = 0;
                if($quotation->id != null){
                    $isQuotationMaterial = QuotationMaterial::where('quotation_id',$quotation->id)->where('material_id',$inventoryComponent['reference_id'])->select('rate_per_unit','unit_id')->first();
                }
                $units = array();
                if(count($isQuotationMaterial) > 0){
                    $materialVersions = MaterialVersion::where('material_id',$inventoryComponent['reference_id'])->where('unit_id',$isQuotationMaterial['unit_id'])->pluck('id');
                    $material_quantity = $quotationProductMaterialData = QuotationProduct::where('quotation_products.quotation_id',$quotation->id)
                        ->join('product_material_relation','quotation_products.product_version_id','=','product_material_relation.product_version_id')
                        ->whereIn('product_material_relation.material_version_id',$materialVersions)
                        ->sum(DB::raw('quotation_products.quantity * product_material_relation.material_quantity'));
                    $units[0]['max_quantity'] = intval($material_quantity);
                    $units[0]['unit'] = $isQuotationMaterial->unit->name;
                    $unitConversionData = UnitConversion::where('unit_1_id',$isQuotationMaterial['unit_id'])->get();
                    $i = 1;
                    foreach($unitConversionData as $key1 => $unitConversion){
                        $units[$i]['max_quantity'] = $material_quantity * $unitConversion['unit_2_value'];
                        $units[$i]['unit'] = $unitConversion->toUnit->name;
                        $i++;
                    }
                }
                $inventoryListingData[$iterator]['material_name'] = $inventoryComponent['name'];
                $inventoryListingData[$iterator]['id'] = $inventoryComponent['id'];
                $inventoryListingData[$iterator]['units'] = $units;
                $inventoryListingData[$iterator]['quantity_in'] = InventoryTransferTypes::join('inventory_component_transfers','inventory_transfer_types.id','=','inventory_component_transfers.transfer_type_id')
                                                                    ->whereIn('inventory_transfer_types.id',$inventoryTransferTypes)
                                                                    ->where('inventory_transfer_types.type','IN')
                                                                    ->sum('inventory_component_transfers.quantity');
                $inventoryListingData[$iterator]['quantity_out'] = InventoryTransferTypes::join('inventory_component_transfers','inventory_transfer_types.id','=','inventory_component_transfers.transfer_type_id')
                                                                    ->whereIn('inventory_transfer_types.id',$inventoryTransferTypes)
                                                                    ->where('inventory_transfer_types.type','OUT')
                                                                    ->sum('inventory_component_transfers.quantity');
                $inventoryListingData[$iterator]['quantity_available'] = (string)($inventoryListingData[$iterator]['quantity_in'] - $inventoryListingData[$iterator]['quantity_out']);
                $iterator++;
            }

            $data['material_list'] = $inventoryListingData;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalMaterialCount = InventoryComponent::where('project_site_id',$request->project_site_id)->where('is_material',true)->count();
            $remainingCount = $totalMaterialCount - $totalSent;
            if($remainingCount > 0 ){
                $page_id = $pageId + 1;
                $next_url = "/inventory/listing";
            }else{
                $next_url = "";
                $page_id = "";
            }

        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Material Listing',
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
}
