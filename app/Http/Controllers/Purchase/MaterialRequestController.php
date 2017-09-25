<?php

namespace App\Http\Controllers\Purchase;
use App\Asset;
use App\Http\Controllers\CustomTraits\MaterialRequestTrait;
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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Mockery\Exception;

class MaterialRequestController extends BaseController{
use MaterialRequestTrait;
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
            $materialRequestComponent = MaterialRequestComponents::where('id',$request['material_request_component_id'])->first();
            $quotationMaterialType = MaterialRequestComponentTypes::where('slug','quotation-material')->first();
            $allowedQuantity = 0;
            if($materialRequestComponent['component_type_id'] == $quotationMaterialType->id){
                $adminApproveComponentStatusId = PurchaseRequestComponentStatuses::where('slug','admin-approved')->pluck('id')->first();
                $usedQuantity = MaterialRequestComponents::where('id','!=',$materialRequestComponent->id)
                                ->where('material_request_id',$materialRequestComponent['material_request_id'])
                                ->where('component_type_id',$quotationMaterialType['id'])
                                ->where('component_status_id',$adminApproveComponentStatusId)
                                ->where('name',$materialRequestComponent['name'])->sum('quantity');
                $quotation = Quotation::where('project_site_id',$request['project_site_id'])->first();
                $quotationMaterialId = Material::whereIn('id',array_column($quotation->quotation_materials->toArray(),'material_id'))
                    ->where('name',$materialRequestComponent->name)->pluck('id')->first();
                $quotationMaterial = QuotationMaterial::where('quotation_id',$quotation->id)->where('material_id',$quotationMaterialId)->first();
                $materialVersions = MaterialVersion::where('material_id',$quotationMaterial['material_id'])->where('unit_id',$quotationMaterial['unit_id'])->pluck('id');
                $material_quantity = QuotationProduct::where('quotation_products.quotation_id',$quotation->id)
                    ->join('product_material_relation','quotation_products.product_version_id','=','product_material_relation.product_version_id')
                    ->whereIn('product_material_relation.material_version_id',$materialVersions)
                    ->sum(DB::raw('quotation_products.quantity * product_material_relation.material_quantity'));
                $allowedQuantity = $material_quantity - $usedQuantity;
            }
            if((int)$materialRequestComponent['quantity'] < $allowedQuantity){
                MaterialRequestComponents::where('material_request_id',$request['material_request_id'])->update(['component_status_id' => $request['change_component_status_id_to']]);
                $message = "Status Updated Successfully";
            }else{
                $message = "Allowed quantity is ".$allowedQuantity;
            }
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
            if(count($materialRequest) > 0){
                foreach($materialRequest->materialRequestComponents as $key => $materialRequestComponents){
                    $materialRequestList[$iterator]['material_request_component_id'] = $materialRequestComponents->id;
                    $materialRequestList[$iterator]['material_request_format'] = $this->getMaterialRequestIDFormat($materialRequest['project_site_id'],$materialRequestComponents['created_at'],$iterator+1);
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

    public function getMaterialRequestIDFormat($project_site_id,$created_at,$serial_no){
        $format = "MR".$project_site_id.date_format($created_at,'y').date_format($created_at,'m').date_format($created_at,'d').$serial_no;
        return $format;
    }
}
