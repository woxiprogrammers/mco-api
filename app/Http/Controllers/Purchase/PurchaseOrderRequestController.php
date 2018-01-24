<?php
    /**
     * Created by Harsha.
     * Date: 23/1/18
     * Time: 10:14 AM
     */

namespace App\Http\Controllers\Purchase;
use App\PurchaseOrderRequest;
use App\PurchaseOrderRequestComponent;
use App\PurchaseRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Mockery\Exception;

class PurchaseOrderRequestController extends BaseController{
        public function __construct(){
            $this->middleware('jwt.auth');
            if(!Auth::guest()){
                $this->user = Auth::user();
            }
        }

        public function getPurchaseOrderRequestListing(Request $request){
            try{
                $purchaseRequestIds = PurchaseRequests::where('project_site_id',$request['project_site_id'])->pluck('id');
                $purchaseOrderRequests = PurchaseOrderRequest::whereIn('purchase_request_id',$purchaseRequestIds)->whereMonth('created_at', $request['month'])->whereYear('created_at', $request['year'])->get();
                $iterator = 0;
                $purchaseOrderRequestList = array();
                foreach($purchaseOrderRequests as $key => $purchaseOrderRequest){
                    $purchaseOrderRequestList[$iterator]['purchase_order_request_id'] = $purchaseOrderRequest['id'];
                    $purchaseOrderRequestList[$iterator]['purchase_request_id'] = $purchaseOrderRequest['purchase_request_id'];
                    $purchaseOrderRequestList[$iterator]['purchase_request_format_id'] = $purchaseOrderRequest->purchaseRequest->format_id;
                    $componentNamesArray = PurchaseOrderRequestComponent::join('purchase_request_component_vendor_relation','purchase_request_component_vendor_relation.id','=','purchase_order_request_components.purchase_request_component_vendor_relation_id')
                        ->join('purchase_request_components','purchase_request_components.id','=','purchase_request_component_vendor_relation.purchase_request_component_id')
                        ->join('material_request_components','purchase_request_components.material_request_component_id','=','material_request_components.id')
                        ->where('purchase_order_request_components.purchase_order_request_id', $purchaseOrderRequest->id)
                        ->distinct('material_request_components.name')
                        ->pluck('material_request_components.name')
                        ->toArray();
                    $purchaseOrderRequestList[$iterator]['component_names'] = implode(', ',$componentNamesArray);
                    $purchaseOrderRequestList[$iterator]['user_name'] = $purchaseOrderRequest->user->first_name.' '.$purchaseOrderRequest->user->last_name;
                    $purchaseOrderRequestList[$iterator]['date'] = date('l, d F Y',strtotime($purchaseOrderRequest['created_at']));

                    $iterator++;
                }
                $status = 200;
                $message = "Success";
                $data['purchase_order_request_list'] = $purchaseOrderRequestList;
            }catch(Exception $e){
                $message = "Fail";
                $status = 500;
                $data = [
                    'action' => 'Get Purchase Order Request Listing',
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