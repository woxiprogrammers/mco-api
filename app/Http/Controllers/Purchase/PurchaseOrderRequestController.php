<?php
    /**
     * Created by Harsha.
     * Date: 23/1/18
     * Time: 10:14 AM
     */

namespace App\Http\Controllers\Purchase;
use App\PurchaseOrder;
use App\PurchaseOrderRequest;
use App\PurchaseOrderRequestComponent;
use App\PurchaseRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

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
                    $purchaseOrderCount = PurchaseOrder::where('purchase_order_request_id',$purchaseOrderRequest['id'])->count();
                    $purchaseOrderRequestList[$iterator]['purchase_order_done'] = ($purchaseOrderCount > 0) ? true : false;
                    $iterator++;
                }
                $status = 200;
                $message = "Success";
                $data['purchase_order_request_list'] = $purchaseOrderRequestList;
            }catch(\Exception $e){
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

        public function getPurchaseOrderRequestDetail(Request $request){
            try{
                $purchaseOrderRequestComponents = array();
                $purchaseOrderRequestComponentData = PurchaseOrderRequestComponent::where('purchase_order_request_id',$request['purchase_order_request_id'])->get();
                $iterator = 0;
                foreach($purchaseOrderRequestComponentData as $purchaseOrderRequestComponent){
                    $purchaseRequestComponentId = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->purchase_request_component_id;
                    if(!array_key_exists($purchaseRequestComponentId,$purchaseOrderRequestComponents)){
                        $materialRequestComponent = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->purchaseRequestComponent->materialRequestComponent;
                        $purchaseOrderRequestComponents[$purchaseRequestComponentId]['material_request_component_id'] = $materialRequestComponent['id'];
                        $purchaseOrderRequestComponents[$purchaseRequestComponentId]['name'] = ucwords($materialRequestComponent->name);
                        $purchaseOrderRequestComponents[$purchaseRequestComponentId]['quantity'] = $purchaseOrderRequestComponent->quantity;
                        $purchaseOrderRequestComponents[$purchaseRequestComponentId]['unit'] = $purchaseOrderRequestComponent->unit->name;
                        $purchaseOrderRequestComponents[$purchaseRequestComponentId]['is_approved'] = ($purchaseOrderRequestComponent->is_approved != null) ? true : false;
                    }
                    $rateWithTax = $purchaseOrderRequestComponent->rate_per_unit;
                    $rateWithTax += ($purchaseOrderRequestComponent->rate_per_unit * ($purchaseOrderRequestComponent->cgst_percentage / 100));
                    $rateWithTax += ($purchaseOrderRequestComponent->rate_per_unit * ($purchaseOrderRequestComponent->sgst_percentage / 100));
                    $rateWithTax += ($purchaseOrderRequestComponent->rate_per_unit * ($purchaseOrderRequestComponent->igst_percentage / 100));
                    $purchaseOrderRequestComponents[$purchaseRequestComponentId]['vendor_relations'][] = [
                        'purchase_order_request_component_id' => $purchaseOrderRequestComponent['id'],
                        'component_vendor_relation_id' => $purchaseOrderRequestComponent->purchase_request_component_vendor_relation_id,
                        'vendor_name' => $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->vendor->company,
                        'vendor_id' => $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->vendor_id,
                        'rate_without_tax' => (string)$purchaseOrderRequestComponent->rate_per_unit,
                        'rate_with_tax' => (string)$rateWithTax,
                        'total_with_tax' => (string)($rateWithTax * $purchaseOrderRequestComponents[$purchaseRequestComponentId]['quantity'])
                    ];
                    $iterator++;
                }
                $data['purchase_order_request_list'] = array_values($purchaseOrderRequestComponents);
                $status = 200;
                $message = "Success";
            }catch(\Exception $e){
                $message = "Fail";
                $status = 500;
                $data = [
                    'action' => 'Get Purchase Order Request Detail',
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

        public function changeStatus(Request $request){
            try{
                foreach ($request['purchase_order_request_components'] as $key => $purchase_order_request_component) {
                    PurchaseOrderRequestComponent::where('purchase_order_request_id',$purchase_order_request_component['id'])
                                ->update(['is_approved' => $purchase_order_request_component['is_approved']]);
                }
                $message = "Component status changed successfully";
                $status = 200;
            }catch(\Exception $e){
              $message = "Fail";
              $status = 500;
              $data = [
                  'action' => 'Purchase Order Request change status',
                  'params' => $request->all(),
                  'exception' => $e->getMessage()
              ];
              Log::critical(json_encode($data));
            }
            return response()->json($message,$status);
        }

    }
