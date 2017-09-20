<?php

namespace App\Http\Controllers\Purchase;
use App\Asset;
use App\Material;
use App\MaterialRequestComponents;
use App\MaterialRequestComponentTypes;
use App\MaterialRequests;
use App\MaterialVersion;
use App\PurchaseRequestComponentStatuses;
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
use Mockery\Exception;

class MaterialRequestController extends BaseController{

    public function __construct(){
        $this->middleware('jwt.auth',['except' => ['autoSuggest']]);
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function createMaterialRequest(Request $request){
        try{
            $status = 200;
            $message = "Success";
            $user = Auth::user();
            $requestData = $request->all();
            $quotationId = Quotation::where('project_site_id',$requestData['project_site_id'])->pluck('id')->first();
            $alreadyCreatedMaterialRequest = MaterialRequests::where('project_site_id',$requestData['project_site_id'])->where('user_id',$user['id'])->get()->toArray();
            if(count($alreadyCreatedMaterialRequest) > 0){
                $materialRequest = $alreadyCreatedMaterialRequest;
            }else{
                $materialRequest['project_site_id'] = $requestData['project_site_id'];
                $materialRequest['user_id'] = $user['id'];
                $materialRequest['quotation_id'] = $quotationId != null ? $quotationId : null;
                $materialRequest['assigned_to'] = $requestData['assigned_to'];
                $materialRequest = MaterialRequests::create($materialRequest);
            }
            foreach($requestData['item_list'] as $key => $itemData){
                $materialRequestComponent['material_request_id'] = $materialRequest->id;
                $materialRequestComponent['name'] = $itemData['name'];
                $materialRequestComponent['quantity'] = $itemData['quantity'];
                $materialRequestComponent['unit_id'] = $itemData['unit_id'];
                $materialRequestComponent['component_type_id'] = $itemData['component_type_id'];
                $materialRequestComponent['component_status_id'] = PurchaseRequestComponentStatuses::where('slug','pending')->pluck('id')->first();
                MaterialRequestComponents::create($materialRequestComponent);
                if(array_has($itemData,'images')){
                    dd(123);
                 //images goes here
                }
            }
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
                    $quotationMaterialId = Material::whereIn('id',array_column($quotation->quotation_materials->toArray(),'material_id'))
                        ->where('name','ilike','%'.$request->keyword.'%')->pluck('id');
                    $quotationMaterials = QuotationMaterial::where('quotation_id',$quotation->id)->whereIn('material_id',$quotationMaterialId)->get();
                    $quotationMaterialSlug = MaterialRequestComponentTypes::where('slug','quotation-material')->first();
                    $materialRequestID = MaterialRequests::where('project_site_id',$request['project_site_id'])->pluck('id')->first();
                    $adminApproveComponentStatusId = PurchaseRequestComponentStatuses::where('slug','admin-approved')->pluck('id')->first();
                    foreach($quotationMaterials as $key => $quotationMaterial){
                        $usedMaterial = MaterialRequestComponents::where('material_request_id',$materialRequestID)->where('component_type_id',$quotationMaterialSlug->id)->where('component_status_id',$adminApproveComponentStatusId)->where('name',$quotationMaterial->material->name)->orderBy('created_at','asc')->get();
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
                        $unitConversionData = UnitConversion::where('unit_1_id',$quotationMaterial->unit_id)->get();
                        $i = 1;
                        foreach($unitConversionData as $key1 => $unitConversion){
                            $materialList[$iterator]['unit_quantity'][$i]['quantity'] = $allowedQuantity * $unitConversion['unit_2_value'];
                            $materialList[$iterator]['unit_quantity'][$i]['unit_id'] = $unitConversion->unit_2_id;
                            $materialList[$iterator]['unit_quantity'][$i]['unit_name'] = $unitConversion->toUnit->name;
                            $i++;
                        }
                        $materialList[$iterator]['material_request_component_type_slug'] = $quotationMaterialSlug->slug;
                        $materialList[$iterator]['material_request_component_type_id'] = $quotationMaterialSlug->id;
                        $iterator++;
                    }
                    $structureMaterials = Material::whereNotIn('id',$quotationMaterialId)->where('name','ilike','%'.$request->keyword.'%')->get();
                    $structureMaterialSlug = MaterialRequestComponentTypes::where('slug','structure-material')->first();
                    foreach($structureMaterials as $key1 => $material){
                        $materialList[$iterator]['material_name'] = $material->name;
                        $materialList[$iterator]['unit_quantity'][0]['quantity'] = null;
                        $materialList[$iterator]['unit_quantity'][0]['unit_id'] = $material->unit_id;
                        $materialList[$iterator]['unit_quantity'][0]['unit_name'] = $material->unit->name;
                        $materialList[$iterator]['material_request_component_type_slug'] = $structureMaterialSlug->slug;
                        $materialList[$iterator]['material_request_component_type_id'] = $structureMaterialSlug->id;
                        $iterator++;
                    }
                    if(count($materialList) == 0){
                        $materialList['material_name'] = null;
                        $systemUnits = Unit::where('is_active',true)->get();
                        $j = 0;
                        foreach($systemUnits as $key2 => $unit){
                            $materialList['unit_quantity'][$j]['quantity'] = null;
                            $materialList['unit_quantity'][$j]['unit_id'] = $unit->id;
                            $materialList['unit_quantity'][$j]['unit_name'] = $unit->name;
                            $j++;
                        }
                        $newMaterialSlug = MaterialRequestComponentTypes::where('slug','new-material')->first();
                        $materialList['material_request_component_type_slug'] = $newMaterialSlug->slug;
                        $materialList['material_request_component_type_id'] = $newMaterialSlug->id;
                    }
                    $data['material_list'] = $materialList;
                break;

                case "asset" :
                    $assetList = array();
                    $alreadyExistAsset = Asset::where('name','ilike','%'.$request['keyword'].'%')->get();
                    foreach ($alreadyExistAsset as $key => $asset){
                        $assetList[$iterator]['asset_id'] = $asset['id'];
                        $assetList[$iterator]['asset_name'] = $asset['name'];
                        $iterator++;
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

    public function changeStatus(Request $request){
        try{
            MaterialRequestComponents::where('material_request_id',$request['material_request_id'])->update(['component_status_id' => $request['change_status_to_id']]);
            $message = "Status Updated Successfully";
            $status = 200;
        }catch(\Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Change status of material request',
                'params' => $request->all(),
                'exception' => $e->getMessage()
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

            $materialRequest = MaterialRequests::where('project_site_id',$request['project_site_id'])->where('user_id',$request['user_id'])->first();
            $materialRequestList = array();
            $iterator = 0;
            foreach($materialRequest->materialRequestComponents as $key => $materialRequestComponents){
                $materialRequestList[$iterator]['materail_request_component_id'] = $materialRequestComponents->id;
                $materialRequestList[$iterator]['name'] = $materialRequestComponents->name;
                $materialRequestList[$iterator]['quantity'] = $materialRequestComponents->quantity;
                $materialRequestList[$iterator]['unit_id'] = $materialRequestComponents->unit_id;
                $materialRequestList[$iterator]['unit'] = $materialRequestComponents->unit->name;
                $materialRequestList[$iterator]['component_type_id'] = $materialRequestComponents->component_type_id;
                $materialRequestList[$iterator]['component_type'] = $materialRequestComponents->materialRequestComponentTypes->name;
                $materialRequestList[$iterator]['component_status_id'] = $materialRequestComponents->component_status_id;
                $materialRequestList[$iterator]['component_status'] = $materialRequestComponents->purchaseRequestComponentStatuses->name;
                $iterator++;
            }
            $data['material_request_list'] = $materialRequestList;
            $status = 200;
            $message = "Success";
        }catch(Exception $e){
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
            "data" => $data,
            "message" => $message,
        ];

        return response()->json($response,$status);
    }
}
