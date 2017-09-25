<?php
    /**
     * Created by Harsha.
     * User: manoj
     * Date: 22/9/17
     * Time: 10:14 AM
     */

namespace App\Http\Controllers\Purchase;
use App\Http\Controllers\CustomTraits\MaterialRequestTrait;
use App\MaterialRequestComponents;
use App\PurchaseRequestComponents;
use App\PurchaseRequestComponentStatuses;
use App\PurchaseRequests;
use App\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Mockery\Exception;

class PurchaseRequestController extends BaseController{
use MaterialRequestTrait;

    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()){
            $this->user = Auth::user();
        }
    }

    public function createPurchaseRequest(Request $request){
        try{
            $status = 200;
            $message = "Success";
            $user = Auth::user();
            $requestData = $request->all();
            $purchaseRequest = array();
            if($request->has('item_list')){
                $materialRequestComponentId = $this->createMaterialRequest($request->except('material_request_component_id'),$user,$is_purchase_request = true);
                $materialRequestComponentIds = array_merge($materialRequestComponentId,$request['material_request_component_id']);
            }else{
                $materialRequestComponentIds = $request['material_request_component_id'];
            }
            $alreadyCreatedPurchaseRequest = PurchaseRequests::where('project_site_id',$requestData['project_site_id'])->where('user_id',$user['id'])->first();
            if(count($alreadyCreatedPurchaseRequest) > 0){
                $purchaseRequest = $alreadyCreatedPurchaseRequest;
            }else{
                $quotationId = Quotation::where('project_site_id',$requestData['project_site_id'])->first();
                if(count($quotationId) > 0){
                    $purchaseRequest['quotation_id'] = $quotationId['id'];
                }
                $purchaseRequest['project_site_id'] = $request['project_site_id'];
                $purchaseRequest['user_id'] = $purchaseRequest['behalf_of_user_id'] = $user['id'];
                $purchaseRequestedStatus = PurchaseRequestComponentStatuses::where('slug','purchase-requested')->first();
                $purchaseRequest['purchase_component_status_id'] = $purchaseRequestedStatus->id;
                $purchaseRequest = PurchaseRequests::create($purchaseRequest);
            }
            foreach($materialRequestComponentIds as $materialRequestComponentId){
                PurchaseRequestComponents::create(['purchase_request_id' => $purchaseRequest['id'], 'material_request_component_id' => $materialRequestComponentId]);
            }
            $PRAssignedStatusId = PurchaseRequestComponentStatuses::where('slug','p-r-assigned')->pluck('id')->first();
            MaterialRequestComponents::whereIn('id',$request['material_request_component_id'])->update(['component_status_id' => $PRAssignedStatusId]);
        }catch (Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Create Purchase Request',
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