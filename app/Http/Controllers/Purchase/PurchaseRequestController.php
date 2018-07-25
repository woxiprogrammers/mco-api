<?php
    /**
     * Created by Harsha.
     * Date: 22/9/17
     * Time: 10:14 AM
     */

namespace App\Http\Controllers\Purchase;
use App\Http\Controllers\CustomTraits\MaterialRequestTrait;
use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\Http\Controllers\CustomTraits\PurchaseTrait;
use App\MaterialRequestComponentHistory;
use App\MaterialRequestComponents;
use App\MaterialRequestComponentVersion;
use App\MaterialRequests;
use App\Module;
use App\Permission;
use App\PurchaseOrder;
use App\PurchaseOrderRequest;
use App\PurchaseOrderRequestComponent;
use App\PurchaseRequestComponents;
use App\PurchaseRequestComponentStatuses;
use App\PurchaseRequestComponentVendorRelation;
use App\PurchaseRequests;
use App\Quotation;
use App\User;
use App\UserHasPermission;
use App\UserLastLogin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class PurchaseRequestController extends BaseController{
use MaterialRequestTrait;
use PurchaseTrait;
use NotificationTrait;
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
            $purchaseRequest = $materialRequestComponentIds = array();
            if($request->has('item_list')){
                $materialRequestComponentId = $this->createMaterialRequest($request->except('material_request_component_id'),$user,$is_purchase_request = true);
                if($request->has('material_request_component')){
                    $iterator = 0;
                    foreach($request['material_request_component'] as $key => $materialRequestData){
                        $materialRequestComponentId[] = $request['material_request_component'][$iterator]['id'];
                        $iterator++;
                    }
                    $materialRequestComponentIds = array_merge($materialRequestComponentIds,$materialRequestComponentId);
                }else{
                    $materialRequestComponentIds = $materialRequestComponentId;
                }
            }elseif($request->has('material_request_component')){
                $iterator = 0;
                $materialRequestComponentId = array();
                foreach($request['material_request_component'] as $key => $materialRequestData){
                    $materialRequestComponentId[] = $request['material_request_component'][$iterator]['id'];
                    $iterator++;
                }
                $materialRequestComponentIds = array_merge($materialRequestComponentIds,$materialRequestComponentId);
            }
            $quotationId = Quotation::where('project_site_id',$requestData['project_site_id'])->first();
            if(count($quotationId) > 0){
                $purchaseRequest['quotation_id'] = $quotationId['id'];
            }
            $purchaseRequest['project_site_id'] = $request['project_site_id'];
            $purchaseRequest['user_id'] = $purchaseRequest['behalf_of_user_id'] = $user['id'];
            $purchaseRequestedStatus = PurchaseRequestComponentStatuses::where('slug','purchase-requested')->first();
            $purchaseRequest['purchase_component_status_id'] = $purchaseRequestedStatus->id;
            $serialNoCount = PurchaseRequests::whereDate('created_at',Carbon::now())->count();
            $purchaseRequest['serial_no']  = $serialNoCount + 1;
            $purchaseRequest['format_id'] = $this->getPurchaseIDFormat('purchase-request',$request['project_site_id'],Carbon::now(),$purchaseRequest['serial_no']);
            $purchaseRequest = PurchaseRequests::create($purchaseRequest);
            $userTokens = User::join('user_has_permissions','users.id','=','user_has_permissions.user_id')
                ->join('permissions','permissions.id','=','user_has_permissions.permission_id')
                ->join('user_project_site_relation','users.id','=','user_project_site_relation.user_id')
                ->whereIn('permissions.name',['approve-purchase-request','create-purchase-order'])
                ->where('user_project_site_relation.project_site_id',$request['project_site_id'])
                ->select('users.web_fcm_token as web_fcm_token', 'users.mobile_fcm_token as mobile_fcm_token')
                ->get()
                ->toArray();
            $webTokens = array_column($userTokens,'web_fcm_token');
            $mobileTokens = array_column($userTokens,'mobile_fcm_token');
            $notificationString = '2 -'.$purchaseRequest->projectSite->project->name.' '.$purchaseRequest->projectSite->name;
            $notificationString .= ' '.$user['first_name'].' '.$user['last_name'].'Purchase Request Created.';
            $this->sendPushNotification('Manisha Construction',$notificationString,$webTokens,$mobileTokens,'c-p-r');
            foreach($materialRequestComponentIds as $materialRequestComponentId){
                PurchaseRequestComponents::create(['purchase_request_id' => $purchaseRequest['id'], 'material_request_component_id' => $materialRequestComponentId]);
            }
            if($request->has('material_request_component')){
                $PRAssignedStatusId = PurchaseRequestComponentStatuses::where('slug','p-r-assigned')->pluck('id')->first();
                $materialComponentHistoryData = array();
                $materialComponentHistoryData['remark'] = '';
                $materialComponentHistoryData['user_id'] = $user['id'];
                $materialComponentHistoryData['component_status_id'] = $PRAssignedStatusId;
                $iterator = 0;
                foreach($request['material_request_component'] as $key => $materialRequestComponentData){
                    MaterialRequestComponents::where('id',$request['material_request_component'][$iterator]['id'])->update(['component_status_id' => $PRAssignedStatusId]);
                    $materialComponentHistoryData['material_request_component_id'] = $materialRequestComponentData['id'];
                    MaterialRequestComponentHistory::create($materialComponentHistoryData);
                    if(array_key_exists('quantity',$request['material_request_component'][$iterator])){
                        $materialRequestComponentVersion['material_request_component_id'] = $materialRequestComponentData['id'];
                        $materialRequestComponentVersion['component_status_id'] = $PRAssignedStatusId;
                        $materialRequestComponentVersion['user_id'] = $user['id'];
                        $materialRequestComponentVersion['quantity'] = round($materialRequestComponentData['quantity'],3);
                        $materialRequestComponentVersion['unit_id'] = $materialRequestComponentData['unit_id'];
                        $materialRequestComponentVersion['remark'] = $materialRequestComponentData['remark'];
                        MaterialRequestComponentVersion::create($materialRequestComponentVersion);
                    }
                    $iterator++;
                }
            }

        }catch (\Exception $e){
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
            $user = Auth::user();
            $materialComponentHistoryData = array();
            $materialComponentHistoryData['remark'] = '';
            $materialComponentHistoryData['user_id'] = $user['id'];
            PurchaseRequests::where('id',$request['purchase_request_id'])->update([
                'purchase_component_status_id' => $request['change_component_status_id_to']
            ]);
            $materialComponentIds = PurchaseRequestComponents::where('purchase_request_id',$request['purchase_request_id'])->pluck('material_request_component_id')->toArray();
            MaterialRequestComponents::whereIn('id',$materialComponentIds)->update(['component_status_id' => $request['change_component_status_id_to']]);
            $materialComponentHistoryData['component_status_id'] = $request['change_component_status_id_to'];
            foreach($materialComponentIds as $materialComponentId) {
                $materialRequestComponentData = MaterialRequestComponents::where('id',$materialComponentId)->first();
                $materialComponentHistoryData['material_request_component_id'] = $materialComponentId;
                MaterialRequestComponentHistory::create($materialComponentHistoryData);
                $materialRequestComponentVersionData = [
                    'material_request_component_id' => $materialComponentId,
                    'component_status_id' => $request['change_component_status_id_to'],
                    'quantity' => $materialRequestComponentData['quantity'],
                    'unit_id' => $materialRequestComponentData['unit_id'],
                    'user_id' => $user['id'],
                ];
                $materialRequestComponentVersion = MaterialRequestComponentVersion::create($materialRequestComponentVersionData);
            }
            $componentStatus = PurchaseRequestComponentStatuses::where('id',$request['change_component_status_id_to'])->pluck('slug')->first();
            $purchaseRequest = PurchaseRequests::findOrFail($request['purchase_request_id']);
            if(in_array($componentStatus,['p-r-manager-disapproved','p-r-admin-disapproved'])){
                $webTokens = [$purchaseRequest->onBehalfOfUser->web_fcm_token];
                $mobileTokens = [$purchaseRequest->onBehalfOfUser->mobile_fcm_token];
                $MRcreatedUsersTokens = User::join('material_requests','material_requests.on_behalf_of','=','users.id')
                    ->join('material_request_components','material_request_components.material_request_id','=','material_requests.id')
                    ->join('purchase_request_components','purchase_request_components.material_request_component_id','=','material_request_components.id')
                    ->join('purchase_requests','purchase_requests.id','=','purchase_request_components.purchase_request_id')
                    ->where('purchase_requests.id', $purchaseRequest->id)
                    ->select('users.mobile_fcm_token','users.web_fcm_token')
                    ->get()
                    ->toArray();
                $webTokens = array_merge($webTokens, array_column($MRcreatedUsersTokens,'web_fcm_token'));
                $mobileTokens = array_merge($mobileTokens, array_column($MRcreatedUsersTokens,'mobile_fcm_token'));
                $notificationString = '2D -'.$purchaseRequest->projectSite->project->name.' '.$purchaseRequest->projectSite->name;
                $notificationString .= ' '.$user['first_name'].' '.$user['last_name'].'Material Disapproved.';
                $notificationString .= ' '.$request->remark;
                $this->sendPushNotification('Manisha Construction',$notificationString,$webTokens,$mobileTokens,'d-p-r');
            }elseif(in_array($componentStatus,['p-r-admin-approved','p-r-manager-approved'])){
                $vendorAssignmentAclUserToken = UserHasPermission::join('permissions','permissions.id','=','user_has_permissions.permission_id')
                    ->join('users','users.id','=','user_has_permissions.user_id')
                    ->join('user_project_site_relation','user_project_site_relation.user_id','users.id')
                    ->where('permissions.name','create-vendor-assignment')
                    ->where('user_project_site_relation.project_site_id',$purchaseRequest['project_site_id'])
                    ->select('users.web_fcm_token as web_fcm_function','users.mobile_fcm_token as mobile_fcm_function')
                    ->get()->toArray();
                $webTokens = array_column($vendorAssignmentAclUserToken,'web_fcm_function');
                $mobileTokens = array_column($vendorAssignmentAclUserToken,'mobile_fcm_function');
                $notificationString = '3 -'.$purchaseRequest->projectSite->project->name.' '.$purchaseRequest->projectSite->name;
                $notificationString .= ' '.$user['first_name'].' '.$user['last_name'].'Purchase Request Approved.';
                $notificationString .= 'PR number: '.$purchaseRequest->format_id;
                $this->sendPushNotification('Manisha Construction',$notificationString,array_unique($webTokens),array_unique($mobileTokens),'p-r-a');
            }
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
            $approvalAclPermissionCount = Permission::join('user_has_permissions','permissions.id','=','user_has_permissions.permission_id')
                ->where('permissions.name','approve-purchase-request')
                ->where('user_has_permissions.user_id',$user['id'])
                ->count();
            $purchaseRequests = PurchaseRequests::where('project_site_id',$request['project_site_id'])
                ->whereMonth('created_at', $request['month'])->whereYear('created_at', $request['year'])
                ->orderBy('created_at','desc')->get();
            if($approvalAclPermissionCount > 0){
                $has_approve_access = true;
            }else{
                $has_approve_access = false;
            }
            $purchaseRequestList = $data = array();
            $iterator = 0;
            if(count($purchaseRequests) > 0){
                foreach($purchaseRequests as $key => $purchaseRequest){
                    $purchaseRequestList[$iterator]['purchase_request_id'] = $purchaseRequest['id'];
                    $purchaseRequestList[$iterator]['purchase_request_format'] = $this->getPurchaseIDFormat('purchase-request',$request['project_site_id'],$purchaseRequest['created_at'],$purchaseRequest['serial_no']);
                    $purchaseRequestList[$iterator]['date'] = date('l, d F Y',strtotime($purchaseRequest['created_at']));
                    $purchaseRequestComponents = $purchaseRequest->purchaseRequestComponents;
                    $material_name = MaterialRequestComponents::whereIn('id',array_column($purchaseRequestComponents->toArray(),'material_request_component_id'))->distinct('id')->select('name')->take(5)->get();
                    $purchaseRequestList[$iterator]['materials'] = $material_name->implode('name', ', ');
                    $purchaseRequestList[$iterator]['component_status_name'] = $purchaseRequest->purchaseRequestComponentStatuses->slug;
                    $createdByUser = User::where('id',$purchaseRequest['user_id'])->select('first_name','last_name')->first();
                    $purchaseRequestList[$iterator]['created_by'] = $createdByUser['first_name'].' '.$createdByUser['last_name'];
                    $purchase_component_status_id = $purchaseRequest['purchase_component_status_id'];
                    if($purchaseRequestList[$iterator]['component_status_name'] == 'p-r-admin-approved' || $purchaseRequestList[$iterator]['component_status_name'] == 'p-r-admin-disapproved' || $purchaseRequestList[$iterator]['component_status_name'] == 'p-r-manager-approved' || $purchaseRequestList[$iterator]['component_status_name'] == 'p-r-manager-disapproved'){
                        $materialRequestComponentId = $purchaseRequestComponents->pluck('material_request_component_id')->first();
                        $userId = MaterialRequestComponentHistory::where('material_request_component_id',$materialRequestComponentId)->where('component_status_id',$purchase_component_status_id)->pluck('user_id')->first();
                        $user = User::where('id',$userId)->select('first_name','last_name')->first();
                        $purchaseRequestList[$iterator]['approved_by'] = $user['first_name'].' '.$user['last_name'];
                    }else{
                        $purchaseRequestList[$iterator]['approved_by'] = '';
                    }
                    $isPurchaseOrderCreated = PurchaseOrder::where('purchase_request_id',$purchaseRequest['id'])->count();
                    $purchaseRequestComponentIds = $purchaseRequestComponents->pluck('id')->toArray();
                    $vendorAssignedCount = PurchaseRequestComponentVendorRelation::whereIn('purchase_request_component_id',$purchaseRequestComponentIds)->count();
                    $purchaseOrderRequestIds = PurchaseOrderRequest::where('purchase_request_id',$purchaseRequest['id'])->pluck('id');
                    if($isPurchaseOrderCreated > 0){
                        $purchaseRequestList[$iterator]['purchase_request_status'] = "Purchase Order Created";
                    }elseif(count($purchaseOrderRequestIds) > 0){
                        $purchaseRequestList[$iterator]['purchase_request_status'] = "Purchase Order Requested";
                    }elseif($vendorAssignedCount > 0){
                        $purchaseRequestList[$iterator]['purchase_request_status'] = "Vendor Assigned";
                    }else{
                        $purchaseRequestList[$iterator]['purchase_request_status'] = "Purchase Request Created";
                    }
                    $purchaseRequestList[$iterator]['is_disapproved']  = false;
                    foreach($purchaseRequestComponentIds as $purchaseRequestComponentId){
                        $purchaseOrderRequestComponentData = PurchaseOrderRequestComponent::join('purchase_request_component_vendor_relation','purchase_request_component_vendor_relation.id','=','purchase_order_request_components.purchase_request_component_vendor_relation_id')
                            ->join('purchase_request_components','purchase_request_components.id','=','purchase_request_component_vendor_relation.purchase_request_component_id')
                            ->where('purchase_request_components.id',$purchaseRequestComponentId)->orderBy('id','desc')->select('purchase_order_request_components.id','purchase_order_request_components.is_approved','purchase_order_request_components.approve_disapprove_by_user','purchase_order_request_components.purchase_request_component_vendor_relation_id')->get();
                        $purchaseOrderRequestComponentNullIds = PurchaseOrderRequestComponent::join('purchase_request_component_vendor_relation','purchase_request_component_vendor_relation.id','=','purchase_order_request_components.purchase_request_component_vendor_relation_id')
                            ->join('purchase_request_components','purchase_request_components.id','=','purchase_request_component_vendor_relation.purchase_request_component_id')
                            ->where('purchase_request_components.id',$purchaseRequestComponentId)->orderBy('id','desc')->whereNull('is_approved')->pluck('purchase_order_request_components.id');
                        if(count($purchaseOrderRequestComponentData) > 0){
                            $disapprovedCount = $purchaseOrderRequestComponentData->where('is_approved',false)->whereNotIn('id',$purchaseOrderRequestComponentNullIds)->count();
                            if($disapprovedCount == count($purchaseOrderRequestComponentData)){

                                $purchaseRequestList[$iterator]['is_disapproved'] = true;
                                break;
                            }
                        }
                    }
                    $iterator++;
                }
            }
            $displayLength = 30;
            $start = ((int)$pageId + 1) * $displayLength;
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
            $purchaseRequestModuleId = Module::where('slug','purchase-request')->pluck('id')->first();
            $userId = Auth::user()->id;
            $lastLogin = UserLastLogin::where('user_id',$userId)->where('module_id',$purchaseRequestModuleId)->first();
            if($lastLogin == null){
                $lastLoginData = [
                    'user_id' => Auth::user()->id,
                    'module_id' => $purchaseRequestModuleId,
                    'last_login' => Carbon::now()
                ];
                UserLastLogin::create($lastLoginData);
            }else{
                $lastLogin->update(['last_login' => Carbon::now()]);
            }
        }catch(\Exception $e){
            $has_approve_access = false;
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
            "has_approve_access" => $has_approve_access,
            "data" => $data,
            "message" => $message,
            "next_url" => $next_url,
            "page_id" => $page_id
        ];
        return response()->json($response,$status);
    }

    public function getDetailListing(Request $request){
        try{
            $iterator = 0;
            $material_list = array();
            $materialRequestComponentIds = PurchaseRequestComponents::where('purchase_request_id',$request['purchase_request_id'])->pluck('material_request_component_id');
            $materialRequestComponentData = MaterialRequestComponents::whereIn('id',$materialRequestComponentIds)->orderBy('id','asc')->get();
            foreach ($materialRequestComponentData as $key => $materialRequestComponent){
                $materialRequest = $materialRequestComponent->materialRequest;
                $material_list[$iterator]['material_request_component_id'] = $materialRequestComponent['id'];
                $material_list[$iterator]['purchase_request_id'] = $request['purchase_request_id'];
                $material_list[$iterator]['material_request_component_format_id'] = $this->getPurchaseIDFormat('material-request-component',$materialRequest->project_site_id,$materialRequestComponent['created_at'],$materialRequestComponent['serial_no']);
                $material_list[$iterator]['material_request_id'] = $materialRequestComponent['material_request_id'];
                $material_list[$iterator]['material_request_format'] = $this->getPurchaseIDFormat('material-request',$materialRequest->project_site_id,$materialRequest['created_at'],$materialRequest['serial_no']);
                $material_list[$iterator]['name'] = $materialRequestComponent['name'];
                $material_list[$iterator]['quantity'] = $materialRequestComponent['quantity'];
                $material_list[$iterator]['unit_id'] = $materialRequestComponent['unit_id'];
                $material_list[$iterator]['unit_name'] = $materialRequestComponent->unit->name;
                $material_list[$iterator]['disapproved_by_user_name'] = '';
                $purchaseOrderRequestComponentData = PurchaseOrderRequestComponent::join('purchase_request_component_vendor_relation','purchase_request_component_vendor_relation.id','=','purchase_order_request_components.purchase_request_component_vendor_relation_id')
                    ->join('purchase_request_components','purchase_request_components.id','=','purchase_request_component_vendor_relation.purchase_request_component_id')
                    ->where('purchase_request_components.material_request_component_id',$materialRequestComponent['id'])->orderBy('id','desc')->select('purchase_order_request_components.id','purchase_order_request_components.is_approved','purchase_order_request_components.approve_disapprove_by_user','purchase_order_request_components.purchase_request_component_vendor_relation_id')->get();
                $purchaseOrderRequestComponentNullIds = PurchaseOrderRequestComponent::join('purchase_request_component_vendor_relation','purchase_request_component_vendor_relation.id','=','purchase_order_request_components.purchase_request_component_vendor_relation_id')
                    ->join('purchase_request_components','purchase_request_components.id','=','purchase_request_component_vendor_relation.purchase_request_component_id')
                    ->where('purchase_request_components.material_request_component_id',$materialRequestComponent['id'])->orderBy('id','desc')->whereNull('is_approved')->pluck('purchase_order_request_components.id');
                if(count($purchaseOrderRequestComponentData) > 0){
                    $disapprovedCount = $purchaseOrderRequestComponentData->where('is_approved',false)->whereNotIn('id',$purchaseOrderRequestComponentNullIds)->count();
                    if($disapprovedCount == count($purchaseOrderRequestComponentData)){
                        $disapprovedUser = $purchaseOrderRequestComponentData->first()->user;
                        $material_list[$iterator]['disapproved_by_user_name'] = $disapprovedUser['first_name'].' '.$disapprovedUser['last_name'];
                    }

                }
               $material_list[$iterator]['list_of_images'] = array();
                $materialRequestComponentImages = $materialRequestComponent->materialRequestComponentImages;
                if(count($materialRequestComponentImages) > 0){
                    $material_list[$iterator]['list_of_images'] = $this->getUploadedImages($materialRequestComponentImages,$materialRequestComponent['id']);
                }else{
                    $material_list[$iterator]['list_of_images'][0]['image_url'] = null;
                }
                $material_list[$iterator]['history_messages'] = array();
                $materialRequestComponentVersions = MaterialRequestComponentVersion::where('material_request_component_id', $materialRequestComponent['id'])->where('show_p_r_detail', true)->get();
                foreach ($materialRequestComponentVersions as $materialRequestComponentVersion){
                    $versionUser = User::findOrFail($materialRequestComponentVersion->user_id);
                    $material_list[$iterator]['history_messages'][] = [
                        'message' => date('l, d F Y',strtotime($materialRequestComponentVersion['created_at'])).' '.$materialRequestComponentVersion['quantity'].' '.$materialRequestComponentVersion->unit->name.' P. R. approved by '.$versionUser->first_name.' '.$versionUser->last_name.' '.$materialRequestComponentVersion->remark
                    ];
                }
                $iterator++;
            }
            $data['item_list'] = $material_list;
            $message = "Success";
            $status = 200;

        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Purchase Request Listing',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "purchase_details_data" => $data,
            "message" => $message,
        ];
        return response()->json($response,$status);
    }

    public function getUploadedImages($materialRequestComponentImages,$materialRequestComponentId){
        $iterator = 0;
        $images = array();
        $sha1MaterialRequestId = sha1($materialRequestComponentId);
        $imageUploadPath = env('MATERIAL_REQUEST_IMAGE_UPLOAD').$sha1MaterialRequestId;
        foreach($materialRequestComponentImages as $index => $image){
            $images[$iterator]['image_url'] = $imageUploadPath.DIRECTORY_SEPARATOR.$image->name;
            $iterator++;
        }
        return $images;
    }
}
