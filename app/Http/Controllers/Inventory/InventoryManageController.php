<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\CustomTraits\InventoryTrait;
use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\Http\Controllers\CustomTraits\UnitTrait;
use App\InventoryComponent;
use App\InventoryComponentTransfers;
use App\InventoryComponentTransferStatus;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class InventoryManageController extends BaseController
{
use InventoryTrait;
use UnitTrait;
use NotificationTrait;
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function getMaterialListing(Request $request){
        try{
            $user = Auth::user();
            $message = "Success";
            $status = 200;
            $displayLength = 30;
            $pageId = $request->page_id;
            $totalRecords = $pageId * $displayLength;
            $inventoryComponents = array();
            if ($request->has('material_name') && $request->material_name != null && $request->material_name != "") {
                $inventoryComponents = InventoryComponent::
                    where('project_site_id',$request->project_site_id)
                    ->where('is_material',true)
                    ->where('name','ilike','%'.$request->material_name.'%')
                    ->skip($totalRecords)->take($displayLength)->get();
            } else {
                $inventoryComponents = InventoryComponent::where('project_site_id',$request->project_site_id)->where('is_material',true)->skip($totalRecords)->take($displayLength)->get();
            }
            $quotation = Quotation::where('project_site_id',$request->project_site_id)->first();
            $inventoryListingData = array();
            $iterator = 0;
            $approvedStatusId = InventoryComponentTransferStatus::where('slug','approved')->pluck('id')->first();
            foreach($inventoryComponents as $key => $inventoryComponent){
                $inventoryTransferTypes = InventoryComponentTransfers::where('inventory_component_id',$inventoryComponent['id'])->pluck('transfer_type_id')->toArray();
                $isQuotationMaterial = null;
                if($quotation != null){
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
                    $units[0]['unit_id'] = $isQuotationMaterial->unit->id;
                    $units[0]['unit_name'] = $isQuotationMaterial->unit->name;
                    $unitConversionData = UnitConversion::where('unit_1_id',$isQuotationMaterial['unit_id'])->get();
                    $i = 1;
                    foreach($unitConversionData as $key1 => $unitConversion){
                        $units[$i]['max_quantity'] = $material_quantity * $unitConversion['unit_2_value'];
                        $unitData = $unitConversion->toUnit;
                        $units[$i]['unit_id'] = $unitData->id;
                        $units[$i]['unit_name'] = $unitData->name;
                        $i++;
                    }
                }
                $inventoryListingData[$iterator]['material_name'] = $inventoryComponent['name'];
                $inventoryListingData[$iterator]['id'] = $inventoryComponent['id'];
                $inventoryListingData[$iterator]['units'] = $units;
                $inventoryComponentInData = InventoryTransferTypes::join('inventory_component_transfers','inventory_transfer_types.id','=','inventory_component_transfers.transfer_type_id')
                                                                    ->whereIn('inventory_transfer_types.id',$inventoryTransferTypes)
                                                                    ->where('inventory_component_transfers.inventory_component_id',$inventoryComponent->id)
                                                                    ->where('inventory_transfer_types.type','IN')
                                                                    ->where('inventory_component_transfer_status_id',$approvedStatusId)
                                                                    ->select('inventory_component_transfers.id','inventory_component_transfers.quantity','inventory_component_transfers.unit_id')->orderBy('inventory_component_transfers.id')->get()->toArray();
                $unitId = Material::where('id',$inventoryComponent['reference_id'])->pluck('unit_id')->first();
                $totalIN = 0;
                foreach($inventoryComponentInData as $key1 => $inventoryComponentINTransfer){
                    if($inventoryComponentINTransfer['unit_id'] == $unitId){
                        $totalIN += $inventoryComponentINTransfer['quantity'];
                    }else{
                        $conversionData = $this->unitConversion($inventoryComponentINTransfer['unit_id'],$unitId,$inventoryComponentINTransfer['quantity']);
                        $totalIN += $conversionData['quantity_to'];
                    }
                }
                $inventoryListingData[$iterator]['quantity_in'] = $totalIN  + $inventoryComponent['opening_stock'];
                $inventoryComponentOutData = InventoryTransferTypes::join('inventory_component_transfers','inventory_transfer_types.id','=','inventory_component_transfers.transfer_type_id')
                                                                    ->whereIn('inventory_transfer_types.id',$inventoryTransferTypes)
                                                                    ->where('inventory_component_transfers.inventory_component_id',$inventoryComponent->id)
                                                                    ->where('inventory_transfer_types.type','OUT')
                                                                    ->where('inventory_component_transfer_status_id',$approvedStatusId)
                                                                    ->select('inventory_component_transfers.id','inventory_component_transfers.quantity','inventory_component_transfers.unit_id')->orderBy('inventory_component_transfers.id')->get()->toArray();
                $totalOUT = 0;
                foreach($inventoryComponentOutData as $key1 => $inventoryComponentOUTTransfer){
                    if($inventoryComponentOUTTransfer['unit_id'] == $unitId){
                        $totalOUT += $inventoryComponentOUTTransfer['quantity'];
                    }else{
                        $conversionData = $this->unitConversion($inventoryComponentOUTTransfer['unit_id'],$unitId,$inventoryComponentOUTTransfer['quantity']);
                        $totalOUT += $conversionData['quantity_to'];
                    }
                }
                $inventoryListingData[$iterator]['quantity_out'] = $totalOUT;
                $inventoryListingData[$iterator]['quantity_available'] = (string)($inventoryListingData[$iterator]['quantity_in'] - $inventoryListingData[$iterator]['quantity_out']);
                $inventoryListingData[$iterator]['unit_id'] = $unitId;
                $inventoryListingData[$iterator]['unit_name'] = Unit::where('id',$unitId)->pluck('name')->first();
                $iterator++;
            }
            $userLastLogin = UserLastLogin::join('modules','modules.id','=','user_last_logins.module_id')
                ->where('modules.slug','component-transfer')
                ->where('user_last_logins.user_id',$user->id)
                ->pluck('user_last_logins.id as user_last_login_id')
                ->first();
            if($userLastLogin != null){
                UserLastLogin::where('id', $userLastLogin)->update(['last_login' => Carbon::now()]);
            }else{
                UserLastLogin::create([
                    'user_id' => $user->id,
                    'module_id' => Module::where('slug','component-transfer')->pluck('id')->first(),
                    'last_login' => Carbon::now()
                ]);
            }
            $data['material_list'] = $inventoryListingData;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalMaterialCount = InventoryComponent::where('project_site_id',$request->project_site_id)->where('is_material',true)->count();
            $remainingCount = $totalMaterialCount - $totalSent;
            if($remainingCount > 0 ){
                $page_id = (string)($pageId + 1);
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

    public function checkAvailableQuantity(Request $request){
        try{
            $units = array();
            $inventoryComponentMaterialId = InventoryComponent::where('id',$request['inventory_component_id'])->pluck('reference_id')->first();
            $materialUnitId = Material::where('id',$inventoryComponentMaterialId)->pluck('unit_id')->first();
            $unitConversionIds1 = UnitConversion::where('unit_1_id',$materialUnitId)->pluck('unit_2_id');
            $unitConversionIds2 = UnitConversion::where('unit_2_id',$materialUnitId)->pluck('unit_1_id');
            $unitConversionNeededIds = array_merge($unitConversionIds1->toArray(),$unitConversionIds2->toArray());
            $unitConversionNeededIds[] = $materialUnitId;
            $iterator = 0;
            foreach ($unitConversionNeededIds as $key => $unitId){
                $unit = Unit::where('id',$unitId)->first();
                $units[$iterator]['quantity'] = 0.0;
                $units[$iterator]['unit_id'] = $unit->id;
                $units[$iterator]['unit_name'] = $unit->name;
                $iterator++;
            }
            $data['allowed_quantity_unit'] = $units;
            $status = 200;
            $message= 'Success';
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Check Available Quantity for Inventory Material',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
            'data' => $data
        ];
        return response()->json($response,$status);
    }

}
