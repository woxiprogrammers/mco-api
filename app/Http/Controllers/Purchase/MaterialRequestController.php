<?php

namespace App\Http\Controllers\Purchase;
use App\MaterialRequestComponentImages;
use App\MaterialRequests;
use App\PurchaseRequestComponentStatuses;
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
            $materialRequest['project_site_id'] = $requestData['project_site_id'];
            $materialRequest['user_id'] = $user['id'];
           // $materialRequest = MaterialRequests::create($materialRequest);
            foreach($requestData['item_list'] as $key => $itemData){
                //$materialRequestComponent['material_request_id'] = $materialRequest->id;
                //dd($itemData);
                $materialRequestComponent['name'] = $itemData['name'];
                $materialRequestComponent['quantity'] = $itemData['quantity'];
                $materialRequestComponent['unit_id'] = 1;
                $materialRequestComponent['component_type_id'] = 2;
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
            $status = 200;
            $message = "Success";
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
            "message" => $message
        ];
        return response()->json($response,$status);
    }
}