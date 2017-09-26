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
use App\MaterialRequests;
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

    public function changeStatus(Request $request){
        try{
            PurchaseRequests::where('id',$request['purchase_request_id'])->update(['purchase_component_status_id' => $request['change_component_status_id_to']]);
            $status = 200;
            $message = "Status Updated Successfully";
        }catch(\Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Change Purchase Request status',
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

    public function purchaseRequestListing(Request $request){
        try{
            $user = Auth::user();
            $pageId = $request->page;
            $purchaseRequests = PurchaseRequests::where('project_site_id',$request['project_site_id'])->where('user_id',$user['id'])->whereMonth('created_at', $request['month'])->whereYear('created_at', $request['year'])->get();
            $purchaseRequestList = $data = array();
            $iterator = 0;
            if(count($purchaseRequests) > 0){
                foreach($purchaseRequests as $key => $purchaseRequest){
                    $purchaseRequestList[$iterator]['purchase_request_id'] = $purchaseRequest['id'];
                    $purchaseRequestList[$iterator]['purchase_request_format'] = $this->getPurchaseRequestIDFormat($request['project_site_id'],$purchaseRequest['created_at'],$iterator+1);
                    $purchaseRequestList[$iterator]['date'] = date('l, d F Y',strtotime($purchaseRequest['created_at']));
                    $material_name = MaterialRequestComponents::whereIn('id',array_column($purchaseRequest->purchaseRequestComponents->toArray(),'material_request_component_id'))->distinct('id')->select('name')->take(5)->get();
                    $purchaseRequestList[$iterator]['materials'] = $material_name->implode('name', ', ');
                    $purchaseRequestList[$iterator]['component_status_name'] = $purchaseRequest->purchaseRequestComponentStatuses->name;
                    $iterator++;
                }
            }
            $displayLength = 10;
            $start = ((int)$pageId) * $displayLength;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalMaterialCount = count($purchaseRequestList);
            $remainingCount = $totalMaterialCount - $totalSent;
            $data['purchase_request_list'] = array();
            for($iterator = $start,$jIterator = 0; $iterator < $totalSent && $jIterator < $totalMaterialCount; $iterator++,$jIterator++){
                $data['purchase_request_list'][] = $purchaseRequestList[$iterator];
            }
            if($remainingCount > 0 ){
                $page_id = (string)($pageId + 1);
                $next_url = "/purchase/purchase-request/listing";
            }else{
                $next_url = "";
                $page_id = "";
            }
            $status = 200;
            $message = "Success";
        }catch(Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Purchase Request Listing',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            $next_url = "";
            $page_id = "";
            Log::critical(json_encode($data));
        }
        $response = [
            "data" => $data,
            "message" => $message,
            "next_url" => $next_url,
            "page_id" => $pageId
        ];
        return response()->json($response,$status);
    }

    public function getPurchaseRequestIDFormat($project_site_id,$created_at,$serial_no){
         $format = "PR".$project_site_id.date_format($created_at,'y').date_format($created_at,'m').date_format($created_at,'d').$serial_no;
        return $format;
    }
}