<?php

namespace App\Http\Controllers\Purchase;
use App\Asset;
use App\Http\Controllers\CustomTraits\MaterialRequestTrait;
use App\Http\Controllers\CustomTraits\PurchaseTrait;
use App\Material;
use App\MaterialRequestComponentHistory;
use App\MaterialRequestComponents;
use App\MaterialRequestComponentTypes;
use App\MaterialRequestComponentVersion;
use App\MaterialRequests;
use App\MaterialVersion;
use App\Permission;
use App\PurchaseRequestComponentStatuses;
use App\Quotation;
use App\QuotationMaterial;
use App\QuotationProduct;
use App\Unit;
use App\UnitConversion;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Mockery\Exception;

class MaterialRequestController extends BaseController{
use MaterialRequestTrait;
use PurchaseTrait;
    public function __construct(){
        $this->middleware('jwt.auth',['except' => ['autoSuggest','getPurchaseRequestComponentStatus']]);
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function createMaterialRequestData(Request $request){
        try{
            $status = 200;
            $message = "Success";
            $user = Auth::user();
            $materialRequestComponentIds = $this->createMaterialRequest($request->all(),$user,$is_purchase_request = false);
        }catch (Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Create Material Request',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message
        ];
        return response()->json($response,$status);
    }

    public function autoSuggest(Request $request){
        try{
            $status = 200;
            $message = "Success";
            $iterator = 0;
            $data = array();
            switch($request->search_in){
                case 'material' :
                    $materialList = array();
                    $quotation = Quotation::where('project_site_id',$request['project_site_id'])->first();
                    if(count($quotation) > 0){
                        $quotationMaterialId = Material::whereIn('id',array_column($quotation->quotation_materials->toArray(),'material_id'))
                            ->where('name','ilike','%'.$request->keyword.'%')->pluck('id');
                        $quotationMaterials = QuotationMaterial::where('quotation_id',$quotation->id)->whereIn('material_id',$quotationMaterialId)->get();
                        $quotationMaterialSlug = MaterialRequestComponentTypes::where('slug','quotation-material')->first();
                        $materialRequestID = MaterialRequests::where('project_site_id',$request['project_site_id'])->pluck('id');
                        $adminApproveComponentStatusId = PurchaseRequestComponentStatuses::where('slug','admin-approved')->pluck('id')->first();
                        foreach($quotationMaterials as $key => $quotationMaterial){
                            $usedMaterial = MaterialRequestComponents::whereIn('material_request_id',$materialRequestID)->where('component_type_id',$quotationMaterialSlug->id)->where('component_status_id',$adminApproveComponentStatusId)->where('name',$quotationMaterial->material->name)->orderBy('created_at','asc')->get();
                            $totalQuantityUsed = 0;
                            foreach($usedMaterial as $index => $material){
                                if($material->unit_id == $quotationMaterial->unit_id){
                                    $totalQuantityUsed += $material->quantity;
                                }else{
                                    $unitConversionValue = UnitConversion::where('unit_1_id',$material->unit_id)->where('unit_2_id',$quotationMaterial->unit_id)->first();
                                    if(count($unitConversionValue) > 0){
                                        $conversionQuantity = $material->quantity * $unitConversionValue->unit_1_value;
                                        $totalQuantityUsed += $conversionQuantity;
                                    }else{
                                        $reverseUnitConversionValue = UnitConversion::where('unit_1_id',$quotationMaterial->unit_id)->where('unit_2_id',$material->unit_id)->first();
                                        $conversionQuantity = $material->quantity / $reverseUnitConversionValue->unit_2_value;
                                        $totalQuantityUsed += $conversionQuantity;
                                    }
                                }
                            }
                            $materialVersions = MaterialVersion::where('material_id',$quotationMaterial['material_id'])->where('unit_id',$quotationMaterial['unit_id'])->pluck('id');
                            $material_quantity = QuotationProduct::where('quotation_products.quotation_id',$quotation->id)
                                ->join('product_material_relation','quotation_products.product_version_id','=','product_material_relation.product_version_id')
                                ->whereIn('product_material_relation.material_version_id',$materialVersions)
                                ->sum(DB::raw('quotation_products.quantity * product_material_relation.material_quantity'));
                            $allowedQuantity = $material_quantity - $totalQuantityUsed;
                            $materialList[$iterator]['material_name'] = $quotationMaterial->material->name;
                            $materialList[$iterator]['unit_quantity'][0]['quantity'] = $allowedQuantity;
                            $materialList[$iterator]['unit_quantity'][0]['unit_id'] = (int)$quotationMaterial->unit_id;
                            $materialList[$iterator]['unit_quantity'][0]['unit_name'] = $quotationMaterial->unit->name;
                            $unitConversionIds1 = UnitConversion::where('unit_1_id',$quotationMaterial->unit_id)->pluck('unit_2_id');
                            $unitConversionIds2 = UnitConversion::where('unit_2_id',$quotationMaterial->unit_id)->pluck('unit_1_id');
                            $unitConversionNeededIds = array_merge($unitConversionIds1->toArray(),$unitConversionIds2->toArray());
                            $i = 1;
                            foreach($unitConversionNeededIds as $unitId){
                                $conversionData = $this->unitConversion($quotationMaterial->unit_id,$unitId,$allowedQuantity);
                                $materialList[$iterator]['unit_quantity'][$i]['quantity'] = $conversionData['quantity_to'];
                                $materialList[$iterator]['unit_quantity'][$i]['unit_id'] = $conversionData['unit_to_id'];
                                $materialList[$iterator]['unit_quantity'][$i]['unit_name'] = $conversionData['unit_to_name'];
                                $i++;
                            }
                            $materialList[$iterator]['material_request_component_type_slug'] = $quotationMaterialSlug->slug;
                            $materialList[$iterator]['material_request_component_type_id'] = $quotationMaterialSlug->id;
                            $iterator++;
                        }
                    }
                    if(count($materialList) == 0){
                        $materialList[$iterator]['material_name'] = null;
                        $systemUnits = Unit::where('is_active',true)->get();
                        $j = 0;
                        foreach($systemUnits as $key2 => $unit){
                            $materialList[$iterator]['unit_quantity'][$j]['quantity'] = null;
                            $materialList[$iterator]['unit_quantity'][$j]['unit_id'] = $unit->id;
                            $materialList[$iterator]['unit_quantity'][$j]['unit_name'] = $unit->name;
                            $j++;
                        }
                        $newMaterialSlug = MaterialRequestComponentTypes::where('slug','new-material')->first();
                        $materialList[$iterator]['material_request_component_type_slug'] = $newMaterialSlug->slug;
                        $materialList[$iterator]['material_request_component_type_id'] = $newMaterialSlug->id;
                    }
                    $data['material_list'] = $materialList;
                break;

                case "asset" :
                    $assetList = array();
                    $alreadyExistAsset = Asset::where('name','ilike','%'.$request['keyword'].'%')->get();
                    $assetUnit = Unit::where('slug','nos')->first();
                    $systemAssetStatus = MaterialRequestComponentTypes::where('slug','system-asset')->first();
                    foreach ($alreadyExistAsset as $key => $asset){
                        $assetList[$iterator]['asset_id'] = $asset['id'];
                        $assetList[$iterator]['asset_name'] = $asset['name'];
                        $assetList[$iterator]['asset_unit'] = $assetUnit['name'];
                        $assetList[$iterator]['asset_unit_id'] = $assetUnit['id'];
                        $assetList[$iterator]['material_request_component_type_slug'] = $systemAssetStatus->slug;
                        $assetList[$iterator]['material_request_component_type_id'] = $systemAssetStatus->id;
                        $assetList[$iterator]['asset_type_slug'] = $asset->assetTypes->slug;
                        $iterator++;
                    }
                    if(count($assetList) == 0){
                        $assetList[$iterator]['asset_id'] = null;
                        $assetList[$iterator]['asset_name'] = null;
                        $assetList[$iterator]['asset_unit'] = $assetUnit['name'];
                        $assetList[$iterator]['asset_unit_id'] = $assetUnit['id'];
                        $newAssetSlug = MaterialRequestComponentTypes::where('slug','new-asset')->first();
                        $assetList[$iterator]['material_request_component_type_slug'] = $newAssetSlug->slug;
                        $assetList[$iterator]['material_request_component_type_id'] = $newAssetSlug->id;
                        $assetList[$iterator]['asset_type_slug'] = null;
                    }
                    $data['asset_list'] = $assetList;
                break;
            }

        }catch(Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'AutoSuggestion',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message,
            "data" => $data
        ];
        return response()->json($response,$status);
    }

    public function unitConversion($unit_from_id,$unit_to_id,$quantity_from){
        $unitConversionData = UnitConversion::where('unit_1_id',$unit_from_id)->where('unit_2_id',$unit_to_id)->first();
        if(count($unitConversionData) > 0){
            $data['quantity_to'] = ($quantity_from == null) ? null :($unitConversionData['unit_2_value'] * $quantity_from) / $unitConversionData['unit_1_value'];
            $data['unit_to_id'] = $unitConversionData->unit_2_id;
            $data['unit_to_name'] = $unitConversionData->toUnit->name;
        }else{
            $reverseUnitConversionData = UnitConversion::where('unit_2_id',$unit_from_id)->where('unit_1_id',$unit_to_id)->first();
            $data['quantity_to'] = ($quantity_from == null) ? null :($reverseUnitConversionData['unit_1_value'] * $quantity_from) / $reverseUnitConversionData['unit_2_value'];
            $data['unit_to_id'] = $reverseUnitConversionData->unit_1_id;
            $data['unit_to_name'] = $reverseUnitConversionData->fromUnit->name;
        }
        return $data;
    }

    public function checkAvailableQuantity(Request $request){
        try{
            $units = array();
            $materialRequestComponent = MaterialRequestComponents::where('id',$request['material_request_component_id'])->first();
            $materialRequestComponentSlug = $materialRequestComponent->materialRequestComponentTypes->slug;
            switch ($materialRequestComponentSlug){
                case 'new-material' :
                    $systemUnits = Unit::where('is_active',true)->select('id','name')->get();
                    $iterator = 0;
                    foreach($systemUnits as $key => $unit){
                        $units[$iterator]['quantity'] = 0.0;
                        $units[$iterator]['unit_id'] = $unit->id;
                        $units[$iterator]['unit_name'] = $unit->name;
                        $iterator++;
                    }
                    break;
                case 'structure-material' :
                    $unitConversionIds1 = UnitConversion::where('unit_1_id',$materialRequestComponent->unit_id)->pluck('unit_2_id');
                    $unitConversionIds2 = UnitConversion::where('unit_2_id',$materialRequestComponent->unit_id)->pluck('unit_1_id');
                    $unitConversionNeededIds = array_merge($unitConversionIds1->toArray(),$unitConversionIds2->toArray());
                    $unitConversionNeededIds[] = $materialRequestComponent->unit_id;
                    $iterator = 0;
                    foreach ($unitConversionNeededIds as $key => $unitId){
                        $unit = Unit::where('id',$unitId)->first();
                        $units[$iterator]['quantity'] = 0.0;
                        $units[$iterator]['unit_id'] = $unit->id;
                        $units[$iterator]['unit_name'] = $unit->name;
                        $iterator++;
                    }
                    break;
                case 'quotation-material' :
                    $quotation = Quotation::where('project_site_id',$request['project_site_id'])->first();
                    $materialName = MaterialRequestComponents::where('id',$request['material_request_component_id'])->pluck('name')->first();
                    $quotationMaterialId = Material::whereIn('id',array_column($quotation->quotation_materials->toArray(),'material_id'))
                        ->where('name',$materialName)->pluck('id')->first();
                    $quotationMaterial = QuotationMaterial::where('quotation_id',$quotation->id)->where('material_id',$quotationMaterialId)->first();
                    $quotationMaterialSlug = MaterialRequestComponentTypes::where('slug','quotation-material')->first();
                    $adminApproveComponentStatusId = PurchaseRequestComponentStatuses::where('slug','admin-approved')->pluck('id')->first();
                    $materialRequestID = MaterialRequests::where('project_site_id',$request['project_site_id'])->pluck('id');
                    $usedMaterial = MaterialRequestComponents::whereIn('material_request_id',$materialRequestID)
                        ->where('component_type_id',$quotationMaterialSlug->id)
                        ->where('component_status_id',$adminApproveComponentStatusId)
                        ->where('name',$quotationMaterial->material->name)
                        ->orderBy('created_at','asc')->get();
                    $totalQuantityUsed = 0;
                    foreach($usedMaterial as $index => $material){
                        if($material->unit_id == $quotationMaterial->unit_id){
                            $totalQuantityUsed += $material->quantity;
                        }else{
                            $unitConversionValue = UnitConversion::where('unit_1_id',$material->unit_id)->where('unit_2_id',$quotationMaterial->unit_id)->first();
                            if(count($unitConversionValue) > 0){
                                $conversionQuantity = $material->quantity * $unitConversionValue->unit_1_value;
                                $totalQuantityUsed += $conversionQuantity;
                            }else{
                                $reverseUnitConversionValue = UnitConversion::where('unit_1_id',$quotationMaterial->unit_id)->where('unit_2_id',$material->unit_id)->first();
                                $conversionQuantity = $material->quantity / $reverseUnitConversionValue->unit_2_value;
                                $totalQuantityUsed += $conversionQuantity;
                            }
                        }
                    }
                    $materialVersions = MaterialVersion::where('material_id',$quotationMaterial['material_id'])->where('unit_id',$quotationMaterial['unit_id'])->pluck('id');
                    $material_quantity = QuotationProduct::where('quotation_products.quotation_id',$quotation->id)
                        ->join('product_material_relation','quotation_products.product_version_id','=','product_material_relation.product_version_id')
                        ->whereIn('product_material_relation.material_version_id',$materialVersions)
                        ->sum(DB::raw('quotation_products.quantity * product_material_relation.material_quantity'));
                    $allowedQuantity = $material_quantity - $totalQuantityUsed;
                    $units = array();
                    $units[0]['quantity']= $allowedQuantity;
                    $units[0]['unit_id'] = (int)$quotationMaterial->unit_id;
                    $units[0]['unit_name'] = $quotationMaterial->unit->name;
                    $unitConversionIds1 = UnitConversion::where('unit_1_id',$quotationMaterial->unit_id)->pluck('unit_2_id');
                    $unitConversionIds2 = UnitConversion::where('unit_2_id',$quotationMaterial->unit_id)->pluck('unit_1_id');
                    $unitConversionNeededIds = array_merge($unitConversionIds1->toArray(),$unitConversionIds2->toArray());
                    $i = 1;
                    foreach($unitConversionNeededIds as $unitId){
                        $conversionData = $this->unitConversion($quotationMaterial->unit_id,$unitId,$allowedQuantity);
                        $units[$i]['quantity'] = $conversionData['quantity_to'];
                        $units[$i]['unit_id'] = $conversionData['unit_to_id'];
                        $units[$i]['unit_name'] = $conversionData['unit_to_name'];
                        $i++;
                    }
                    break;
            }

            $data['allowed_quantity_unit'] = $units;
            $status = 200;
            $message= 'Success';
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Check Available Quantity',
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

    public function changeStatus(Request $request){
        try{
            $user = Auth::user();
            $materialRequestComponent = MaterialRequestComponents::where('id',$request['material_request_component_id'])->first();
            $materialComponentHistoryData = array();
            $materialComponentHistoryData['component_status_id'] = $request['change_component_status_id_to'];
            $materialComponentHistoryData['user_id'] = $user['id'];
            $materialComponentHistoryData['material_request_component_id'] = $materialRequestComponent['id'];
            $materialComponentHistoryData['remark'] = $request['remark'];
            if($request->has('quantity','unit_id')){
                MaterialRequestComponents::where('id',$request['material_request_component_id'])->update([
                    'quantity' => $request['quantity'],
                    'unit_id' => $request['unit_id'],
                    'component_status_id' => $request['change_component_status_id_to']
                ]);
                $materialRequestComponentVersion['material_request_component_id'] = $request['material_request_component_id'];
                $materialRequestComponentVersion['component_status_id'] = $request['change_component_status_id_to'];
                $materialRequestComponentVersion['user_id'] = $user['id'];
                $materialRequestComponentVersion['quantity'] = $request['quantity'];
                $materialRequestComponentVersion['unit_id'] = $request['unit_id'];
                $materialRequestComponentVersion['remark'] = $request['remark'];
                MaterialRequestComponentVersion::create($materialRequestComponentVersion);
                $message = "Material Request Edited and Status updated Successfully";
            }else{
                MaterialRequestComponents::where('id',$request['material_request_component_id'])->update(['component_status_id' => $request['change_component_status_id_to']]);
                $message = "Status updated Successfully";
            }
            MaterialRequestComponentHistory::create($materialComponentHistoryData);

            $status = 200;
        }catch(\Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Change status of material request',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message,
        ];
        return response()->json($response,$status);
    }

    public function materialRequestListing(Request $request){
        try{
            $user = Auth::user();
            $materialRequests = MaterialRequests::where('project_site_id',$request['project_site_id'])->orderBy('created_at','desc')->get();
            $approvalAclPermissionCount = Permission::join('user_has_permissions','permissions.id','=','user_has_permissions.permission_id')
                                        ->where('permissions.name','approve-material-request')
                                        ->where('user_has_permissions.user_id',$user['id'])
                                        ->count();
            if($approvalAclPermissionCount > 0){
                $has_approve_access = true;
            }else{
                $has_approve_access = false;
            }
            $materialRequestList = array();
            $iterator = 0;
            if(count($materialRequests) > 0){
                foreach($materialRequests as $index => $materialRequest){
                    foreach($materialRequest->materialRequestComponents as $key => $materialRequestComponents){
                        $materialRequestList[$iterator]['material_request_component_id'] = $materialRequestComponents->id;
                        $materialRequestList[$iterator]['material_request_component_format'] = $this->getPurchaseIDFormat('material-request-component',$materialRequest['project_site_id'],$materialRequestComponents['created_at'],$materialRequestComponents['serial_no']);
                        $materialRequestList[$iterator]['material_request_id'] = $materialRequest->id;
                        $materialRequestList[$iterator]['material_request_format'] = $this->getPurchaseIDFormat('material-request',$materialRequest['project_site_id'],$materialRequest['created_at'],$materialRequest['serial_no']);
                        $materialRequestList[$iterator]['name'] = $materialRequestComponents->name;
                        $materialRequestList[$iterator]['quantity'] = $materialRequestComponents->quantity;
                        $materialRequestList[$iterator]['unit_id'] = $materialRequestComponents->unit_id;
                        $materialRequestList[$iterator]['unit'] = $materialRequestComponents->unit->name;
                        $materialRequestList[$iterator]['component_type_id'] = $materialRequestComponents->component_type_id;
                        $materialRequestList[$iterator]['component_type'] = $materialRequestComponents->materialRequestComponentTypes->name;
                        $materialRequestList[$iterator]['component_status_id'] = $materialRequestComponents->component_status_id;
                        $materialRequestList[$iterator]['component_status'] = $materialRequestComponents->purchaseRequestComponentStatuses->slug;
                        $materialRequestList[$iterator]['created_at'] = date($materialRequestComponents->created_at);
                        $createdByUser = User::where('id',$materialRequest['user_id'])->select('first_name','last_name')->first();
                        $materialRequestList[$iterator]['created_by'] = $createdByUser['first_name'].' '.$createdByUser['last_name'];
                        if($materialRequestList[$iterator]['component_status'] == 'manager-approved' || $materialRequestList[$iterator]['component_status'] == 'manager-disapproved'|| $materialRequestList[$iterator]['component_status'] == 'admin-approved'|| $materialRequestList[$iterator]['component_status'] == 'admin-disapproved'|| $materialRequestList[$iterator]['component_status'] == 'p-r-admin-approved' || $materialRequestList[$iterator]['component_status'] == 'p-r-admin-disapproved' || $materialRequestList[$iterator]['component_status'] == 'p-r-manager-approved' || $materialRequestList[$iterator]['component_status'] == 'p-r-manager-disapproved' || $materialRequestList[$iterator]['component_status'] == 'purchase-requested'){
                            $userId = MaterialRequestComponentHistory::where('material_request_component_id',$materialRequestComponents->id)->where('component_status_id',$materialRequestList[$iterator]['component_status_id'])->pluck('user_id')->first();
                            $user = User::where('id',$userId)->select('first_name','last_name')->first();
                            $materialRequestList[$iterator]['approved_by'] = $user['first_name'].' '.$user['last_name'];
                        }else{
                            $materialRequestList[$iterator]['approved_by'] = '';
                        }

                        $iterator++;
                    }
                }
            }
            $data['material_request_list'] = $materialRequestList;
            $status = 200;
            $message = "Success";
        }catch(Exception $e){
            $has_approve_access = false;
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Material Request Listing',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "has_approve_access" => $has_approve_access,
            "data" => $data,
            "message" => $message
        ];

        return response()->json($response,$status);
    }

    public function getPurchaseRequestComponentStatus(Request $request){
        try{
            $data['status'] = PurchaseRequestComponentStatuses::get()->toArray();
            $message = "Success";
            $status = 200;
        }catch(Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Purchase Request Component Status',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "data" => $data,
            "message" => $message,
        ];
        return response()->json($response,$status);
    }
}
