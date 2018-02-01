<?php

namespace App\Http\Controllers\Purchase;
use App\Asset;
use App\Http\Controllers\CustomTraits\MaterialRequestTrait;
use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\Http\Controllers\CustomTraits\PurchaseTrait;
use App\Material;
use App\MaterialRequestComponentHistory;
use App\MaterialRequestComponents;
use App\MaterialRequestComponentTypes;
use App\MaterialRequestComponentVersion;
use App\MaterialRequests;
use App\MaterialVersion;
use App\Module;
use App\Permission;
use App\PurchaseOrder;
use App\PurchaseOrderComponent;
use App\PurchaseRequestComponentStatuses;
use App\Quotation;
use App\QuotationMaterial;
use App\QuotationProduct;
use App\Unit;
use App\UnitConversion;
use App\User;
use App\UserLastLogin;
use Carbon\Carbon;
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
    use NotificationTrait;
    public function changeStatus(Request $request){
        try{
            $user = Auth::user();
            $materialRequestComponentData = MaterialRequestComponents::where('id',$request['material_request_component_id'])->first();
            $materialComponentHistoryData = array();
            $materialComponentHistoryData['component_status_id'] = $request['change_component_status_id_to'];
            $materialComponentHistoryData['user_id'] = $user['id'];
            $materialComponentHistoryData['material_request_component_id'] = $materialRequestComponentData['id'];
            $materialComponentHistoryData['remark'] = $request['remark'];
            $componentStatus = PurchaseRequestComponentStatuses::where('id',$request['change_component_status_id_to'])->pluck('slug')->first();
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
                $materialRequestComponentData->update(['component_status_id' => $request['change_component_status_id_to']]);
                $materialRequestComponentVersion['material_request_component_id'] = $materialRequestComponentData['id'];
                $materialRequestComponentVersion['component_status_id'] = $request['change_component_status_id_to'];
                $materialRequestComponentVersion['user_id'] = $user['id'];
                $materialRequestComponentVersion['quantity'] = $materialRequestComponentData['quantity'];
                $materialRequestComponentVersion['unit_id'] = $materialRequestComponentData['unit_id'];
                $materialRequestComponentVersion['remark'] = $request['remark'];
                MaterialRequestComponentVersion::create($materialRequestComponentVersion);
                $message = "Status updated Successfully";
            }
            MaterialRequestComponentHistory::create($materialComponentHistoryData);
            if(in_array($componentStatus,['manager-disapproved','admin-disapproved'])){
                $userTokens = User::join('material_requests','material_requests.on_behalf_of','=','users.id')
                    ->join('material_request_components','material_request_components.material_request_id','=','material_requests.id')
                    ->where('material_request_components.id', $request['material_request_component_id'])
                    ->select('users.mobile_fcm_token','users.web_fcm_token')
                    ->get()
                    ->toArray();
                $materialRequestComponent = MaterialRequestComponents::findOrFail($request['material_request_component_id']);
                $tokens = array_merge(array_column($userTokens,'web_fcm_token'), array_column($userTokens,'mobile_fcm_token'));
                $notificationString = '1D -'.$materialRequestComponent->materialRequest->projectSite->project->name.' '.$materialRequestComponent->materialRequest->projectSite->name;
                $notificationString .= ' '.$user['first_name'].' '.$user['last_name'].'Material Disapproved.';
                $notificationString .= ' '.$request['remark'];
                $this->sendPushNotification('Manisha Construction',$notificationString,$tokens,'d-m-r');
            }
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
            if($request->has('keyword')){
                $materialRequests = MaterialRequests::join('material_request_components','material_requests.id','=','material_request_components.material_request_id')
                                            ->where('material_request_components.name','ilike','%'.$request['keyword'].'%')
                                            ->where('material_requests.project_site_id',$request['project_site_id'])
                                            ->orderBy('material_requests.created_at','desc')->select('material_requests.id','material_requests.project_site_id','material_requests.created_at','material_requests.serial_no','material_requests.user_id')->get();
            }else{
                $materialRequests = MaterialRequests::where('project_site_id',$request['project_site_id'])->orderBy('created_at','desc')->get();
            }
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
                        if($materialRequestList[$iterator]['component_status'] == 'manager-approved' || $materialRequestList[$iterator]['component_status'] == 'manager-disapproved'|| $materialRequestList[$iterator]['component_status'] == 'admin-approved'|| $materialRequestList[$iterator]['component_status'] == 'admin-disapproved'|| $materialRequestList[$iterator]['component_status'] == 'p-r-admin-approved' || $materialRequestList[$iterator]['component_status'] == 'p-r-admin-disapproved' || $materialRequestList[$iterator]['component_status'] == 'p-r-manager-approved' || $materialRequestList[$iterator]['component_status'] == 'p-r-manager-disapproved' || $materialRequestList[$iterator]['component_status'] == 'purchase-requested' || $materialRequestList[$iterator]['component_status'] == 'in-indent'){
                            $userId = MaterialRequestComponentHistory::where('material_request_component_id',$materialRequestComponents->id)->where('component_status_id',$materialRequestList[$iterator]['component_status_id'])->pluck('user_id')->first();
                            $user = User::where('id',$userId)->select('first_name','last_name')->first();
                            $materialRequestList[$iterator]['approved_by'] = $user['first_name'].' '.$user['last_name'];
                        }else{
                            $materialRequestList[$iterator]['approved_by'] = '';
                        }
                        $purchaseOrderCount = PurchaseOrderComponent::join('purchase_request_components','purchase_request_components.id','=','purchase_order_components.purchase_request_component_id')
                                                ->join('material_request_components','material_request_components.id','=','purchase_request_components.material_request_component_id')
                                                ->where('material_request_components.id',$materialRequestComponents->id)->count();
                        $materialRequestList[$iterator]['is_purchase_order_created'] = ($purchaseOrderCount > 0) ? true : false;

                        $iterator++;
                    }
                }
            }
            $data['material_request_list'] = $materialRequestList;
            $status = 200;
            $message = "Success";
            $materialRequestModuleId = Module::where('slug','material-request')->pluck('id')->first();
            $userId = Auth::user()->id;
            $lastLogin = UserLastLogin::where('user_id',$userId)->where('module_id',$materialRequestModuleId)->first();
            if($lastLogin == null){
                $lastLoginData = [
                    'user_id' => Auth::user()->id,
                    'module_id' => $materialRequestModuleId,
                    'last_login' => Carbon::now()
                ];
                UserLastLogin::create($lastLoginData);
            }else{
                $lastLogin->update(['last_login' => Carbon::now()]);
            }
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

    public function getPurchaseRequestHistory(Request $request){
        try{
            $data = array();
            $materialRequestComponent = MaterialRequestComponents::where('id',$request['material_request_component_id'])->first();
            $materialComponentVersions = $materialRequestComponent->materialRequestComponentVersion;
            $iterator = 0;
            foreach($materialComponentVersions as $key => $materialComponentVersion){
                $materialRequestComponentStatusSlug = $materialComponentVersion->purchaseRequestComponentStatuses->slug;
                $user = $materialComponentVersion->user;
                switch ($materialRequestComponentStatusSlug){
                    case 'pending' :
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' material requested by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'manager-approved' :
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' material approved by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'manager-disapproved':
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' material disapproved by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'admin-approved' :
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' material approved by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'admin-disapproved':
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' material disapproved by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'in-indent':
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' material moved to Purchase by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'p-r-assigned':
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' P. R. created by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'p-r-manager-approved':
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' P. R. approved by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'p-r-manager-disapproved':
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' P. R. disapproved by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'p-r-admin-approved':
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' P. R. approved by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'p-r-admin-disapproved':
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' P. R. disapproved by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;

                    case 'purchase-requested':
                        $data[$iterator]['id'] = $iterator;
                        $data[$iterator]['display_message'] = date('l, d F Y',strtotime($materialComponentVersion['created_at'])).' '.$materialComponentVersion['quantity'].' '.$materialComponentVersion->unit->name.' purchase requested by '.$user->first_name.' '.$user->last_name.''.$materialComponentVersion->remark;
                        break;
                }
                $iterator++;
            }
            $purchaseOrderComponent = PurchaseOrderComponent::join('purchase_request_components','purchase_request_components.id','=','purchase_order_components.purchase_request_component_id')
                                                                ->join('purchase_orders','purchase_orders.id','=','purchase_order_components.purchase_order_id')
                                                                ->join('purchase_requests','purchase_requests.id','=','purchase_request_components.purchase_request_id')
                                                                ->join('material_request_components','material_request_components.id','=','purchase_request_components.material_request_component_id')
                                                                ->where('material_request_components.id',$request['material_request_component_id'])
                                                                ->select('purchase_orders.user_id','purchase_orders.is_approved','purchase_orders.purchase_order_status_id','purchase_orders.created_at','purchase_order_components.quantity','purchase_order_components.unit_id','purchase_order_components.remark')
                                                                ->first();
            if($purchaseOrderComponent != null){
                $unitName = Unit::where('id',$purchaseOrderComponent['unit_id'])->pluck('name')->first();
                $user = User::where('id',$purchaseOrderComponent['user_id'])->first();
                $data[$iterator]['id'] = $iterator;
                $data[$iterator]['display_message'] = date('l, d F Y',strtotime($purchaseOrderComponent['created_at'])).' '.$purchaseOrderComponent['quantity'].' '.$unitName.' purchase order created by '.$user->first_name.' '.$user->last_name.''.$purchaseOrderComponent['remark'];
            }
            $status = 200;
            $message = "Success";
        }catch(\Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Get Purchase History',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message,
            "data" => array_values($data)
        ];
        return response()->json($response,$status);
    }
}
