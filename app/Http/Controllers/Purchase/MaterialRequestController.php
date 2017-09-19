<?php

namespace App\Http\Controllers\Purchase;
use App\Material;
use App\MaterialRequestComponentImages;
use App\MaterialRequestComponentTypes;
use App\MaterialRequests;
use App\PurchaseRequestComponentStatuses;
use App\Quotation;
use App\QuotationMaterial;
use App\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $materialRequest['project_site_id'] = $requestData['project_site_id'];
            $materialRequest['user_id'] = $user['id'];
            $materialRequest['quotation_id'] = $quotationId != null ? $quotationId : null;
            $materialRequest['assigned_to'] = $requestData['assigned_to'];
            $materialRequest = MaterialRequests::create($materialRequest);
            foreach($requestData['item_list'] as $key => $itemData){
                $materialRequestComponent['material_request_id'] = $materialRequest->id;
                $materialRequestComponent['name'] = $itemData['name'];
                $materialRequestComponent['quantity'] = $itemData['quantity'];
                $materialRequestComponent['unit_id'] = $itemData['unit_id'];
                $materialRequestComponent['component_type_id'] = $itemData['component_type_id'];
                $materialRequestComponent['component_status_id'] = PurchaseRequestComponentStatuses::where('slug','pending')->pluck('id')->first();
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
            //dd($request->all());
            $status = 200;
            $message = "Success";
            $iterator = 0;
            switch($request->search_in){
                case 'material' :
                    $materialList = array();
                    $quotation = Quotation::where('project_site_id',$request['project_site_id'])->first();
                    $quotationMaterialId = Material::whereIn('id',array_column($quotation->quotation_materials->toArray(),'material_id'))
                        ->where('name','ilike','%'.$request->keyword.'%')->pluck('id');
                    $quotationMaterials = QuotationMaterial::where('quotation_id',$quotation->id)->whereIn('material_id',$quotationMaterialId)->get();
                    $quotationMaterialSlug = MaterialRequestComponentTypes::where('slug','quotation-material')->first();
                    foreach($quotationMaterials as $key => $quotationMaterial){
                        $materialList[$iterator]['material_name'] = $quotationMaterial->material->name;
                        $materialList[$iterator]['quantity'] = $quotationMaterial->quantity;
                        $materialList[$iterator]['unit_id'] = $quotationMaterial->unit_id;
                        $materialList[$iterator]['unit_name'] = $quotationMaterial->unit->name;
                        $materialList[$iterator]['material_request_component_type_slug'] = $quotationMaterialSlug->slug;
                        $materialList[$iterator]['material_request_component_type_id'] = $quotationMaterialSlug->id;
                        $iterator++;
                    }
                    $structureMaterials = Material::whereNotIn('id',$quotationMaterialId)->where('name','ilike','%'.$request->keyword.'%')->get();
                    $structureMaterialSlug = MaterialRequestComponentTypes::where('slug','structure-material')->first();
                    foreach($structureMaterials as $key1 => $material){
                        $materialList[$iterator]['material_name'] = $material->name;
                        $materialList[$iterator]['quantity'] = null;
                        $materialList[$iterator]['unit_id'] = $material->unit_id;
                        $materialList[$iterator]['unit_name'] = $material->unit->name;
                        $materialList[$iterator]['material_request_component_type_slug'] = $structureMaterialSlug->slug;
                        $materialList[$iterator]['material_request_component_type_id'] = $structureMaterialSlug->id;
                        $iterator++;
                    }
                    if(count($materialList) == 0){
                        $materialList['quantity'] = $materialList['material_name'] = null;
                        $systemUnits = Unit::where('is_active',true)->get();
                        $i = 0;
                        $materialList['unit'] = array();
                        foreach($systemUnits as $key2 => $unit){
                            $materialList['unit'][$i]['unit_id'] = $unit->id;
                            $materialList['unit'][$i]['unit_name'] = $unit->name;
                            $i++;
                        }
                        $newMaterialSlug = MaterialRequestComponentTypes::where('slug','new-material')->first();
                        $materialList['material_request_component_type_slug'] = $newMaterialSlug->slug;
                        $materialList['material_request_component_type_id'] = $newMaterialSlug->id;
                    }

                case "asset" :
                    $assetList = array();
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
            "material" => $materialList
        ];
        return response()->json($response,$status);
    }
}