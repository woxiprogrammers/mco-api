<?php
    /**
     * Created by Harsha.
     * Date: 5/10/17
     * Time: 11:18 AM
     */

namespace App\Http\Controllers\Purchase;

use App\Asset;
use App\GRNCount;
use App\Http\Controllers\CustomTraits\InventoryTrait;
use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\Http\Controllers\CustomTraits\PurchaseTrait;
use App\InventoryComponent;
use App\InventoryComponentTransferImage;
use App\InventoryTransferTypes;
use App\Material;
use App\MaterialRequestComponents;
use App\MaterialRequestComponentTypes;
use App\MaterialRequestComponentVersion;
use App\PaymentType;
use App\Permission;
use App\PurchaseOrder;
use App\PurchaseOrderBill;
use App\PurchaseOrderBillImage;
use App\PurchaseOrderBillPayment;
use App\PurchaseOrderBillStatus;
use App\PurchaseOrderComponent;
use App\PurchaseOrderComponentImage;
use App\PurchaseOrderStatus;
use App\PurchaseOrderTransaction;
use App\PurchaseOrderTransactionComponent;
use App\PurchaseOrderTransactionImage;
use App\PurchaseOrderTransactionStatus;
use App\PurchasePeticashTransactionImage;
use App\PurchaseRequestComponents;
use App\PurchaseRequests;
use App\User;
use App\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Lumen\Routing\Controller as BaseController;

class PurchaseOrderController extends BaseController{
    use PurchaseTrait;
    use InventoryTrait;
    use NotificationTrait;

    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()){
            $this->user = Auth::user();
        }
    }

    public function getPurchaseOrderListing(Request $request){
        try{
            $pageId = $request->page;
            $user = Auth::user();
            $createAclPermissionCount = Permission::join('user_has_permissions','permissions.id','=','user_has_permissions.permission_id')
                ->where('permissions.name','create-purchase-bill')
                ->where('user_has_permissions.user_id',$user['id'])
                ->count();
            if($createAclPermissionCount > 0){
                $has_create_access = true;
            }else{
                $has_create_access = false;
            }
            if($request->has('purchase_request_id')){
                $purchaseOrderDetail = PurchaseOrder::where('purchase_request_id',$request['purchase_request_id'])->orderBy('created_at','desc')->get();
            }else{
                $purchaseRequestIds = PurchaseRequests::where('project_site_id',$request['project_site_id'])->pluck('id');
                if ($request->has('search_format_id') && $request->search_format_id != "" && $request->search_format_id != null) {
                    $purchaseOrderDetail = PurchaseOrder::whereIn('purchase_request_id',$purchaseRequestIds)->where('format_id','ilike','%'.$request->search_format_id.'%')
                         ->orderBy('created_at','desc')->get();
                } else {
                    $purchaseOrderDetail = PurchaseOrder::whereIn('purchase_request_id',$purchaseRequestIds)->orderBy('created_at','desc')->get();
                }
                
            }
            $purchaseRequest = PurchaseRequests::where('id',$request['purchase_request_id'])->first();
            $purchaseOrderList = array();
            $iterator = 0;
            if(count($purchaseOrderDetail) > 0){
                foreach($purchaseOrderDetail as $key => $purchaseOrder){
                    $purchaseOrderList[$iterator]['purchase_order_id'] = $purchaseOrder['id'];
                    $projectSite = $purchaseOrder->purchaseRequest->projectSite;
                    $purchaseOrderList[$iterator]['purchase_order_format_id'] = $this->getPurchaseIDFormat('purchase-order',$projectSite['id'],$purchaseOrder['created_at'],$purchaseOrder['serial_no']);
                    $purchaseOrderList[$iterator]['purchase_request_id'] = $purchaseOrder['purchase_request_id'];
                    $purchaseOrderList[$iterator]['purchase_request_format_id'] = $this->getPurchaseIDFormat('purchase-request',$projectSite['id'],$purchaseRequest['created_at'],$purchaseRequest['serial_no']);
                    $project = $projectSite->project;
                    if($purchaseOrder->is_client_order == true){
                        $purchaseOrderList[$iterator]['vendor_id'] = $purchaseOrder['client_id'];
                        $purchaseOrderList[$iterator]['vendor_name'] = $purchaseOrder->client->company;
                        $purchaseOrderList[$iterator]['is_client_order'] = true;
                    }else{
                        $purchaseOrderList[$iterator]['vendor_id'] = $purchaseOrder['vendor_id'];
                        $purchaseOrderList[$iterator]['vendor_name'] = $purchaseOrder->vendor->company;
                        $purchaseOrderList[$iterator]['is_client_order'] = false;
                    }
                    $purchaseOrderList[$iterator]['client_name'] = $project->client->company;
                    $purchaseOrderList[$iterator]['project'] = $project->name;
                    $purchaseOrderList[$iterator]['date'] = date($purchaseOrder['created_at']);
                    $purchaseOrderComponents = $purchaseOrder->purchaseOrderComponent;
                    $quantity = ($purchaseOrderComponents->sum('quantity') + ($purchaseOrderComponents->sum('quantity') * (10/100)));
                    $consumedQuantity = 0;
                    foreach($purchaseOrderComponents as $purchaseOrderComponent){
                        $consumedQuantity += $purchaseOrderComponent->purchaseOrderTransactionComponent->sum('quantity');
                    }
                    $purchaseOrderList[$iterator]['purchase_order_status_slug'] = ($purchaseOrder['purchase_order_status_id'] != null) ? $purchaseOrder->purchaseOrderStatus->slug : '';
                    $purchaseOrderList[$iterator]['remaining_quantity'] = (string)($quantity - $consumedQuantity);
                    if($purchaseOrder['is_email_sent'] == null){
                        $purchaseOrderList[$iterator]['is_email_sent'] = true;
                    }else{
                        $purchaseOrderList[$iterator]['is_email_sent'] = $purchaseOrder['is_email_sent'];
                    }
                    $purchaseRequestComponentIds = $purchaseOrderComponents->pluck('purchase_request_component_id');
                    $material_names = MaterialRequestComponents::join('purchase_request_components','material_request_components.id','=','purchase_request_components.material_request_component_id')
                        ->whereIn('purchase_request_components.id',$purchaseRequestComponentIds)
                        ->distinct('material_request_components.name')->select('material_request_components.name')->take(5)->get();
                    $purchaseOrderList[$iterator]['materials'] = $material_names->implode('name', ', ');
                    $purchaseOrderList[$iterator]['status'] = ($purchaseOrder['is_approved'] == true) ? 'Approved' : 'Disapproved';
                    $alreadyGRNGenerated = PurchaseOrderTransaction::where('purchase_order_id',$purchaseOrder['id'])
                                            ->where('purchase_order_transaction_status_id',PurchaseOrderTransactionStatus::where('slug','grn-generated')->pluck('id')->first())
                                            ->orderBy('created_at','desc')->pluck('grn')->first();
                    $purchaseOrderList[$iterator]['images'] = array();
                    if(($alreadyGRNGenerated) != null){
                        $transactionImages = PurchaseOrderTransactionImage::join('purchase_order_transactions','purchase_order_transactions.id','=','purchase_order_transaction_images.purchase_order_transaction_id')
                                            ->where('purchase_order_transactions.grn',$alreadyGRNGenerated)
                                            ->where('purchase_order_transaction_images.is_pre_grn', true)
                                            ->select('purchase_order_transactions.id as transaction_id','purchase_order_transaction_images.name as name')
                                            ->get();
                        $jIterator = 0;
                        $sha1PurchaseOrderId = sha1($purchaseOrder['id']);
                        foreach($transactionImages as $image){
                            $sha1PurchaseOrderTransactionId = sha1($image['transaction_id']);
                            $purchaseOrderList[$iterator]['images'][$jIterator]['image_url'] = env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill_transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderTransactionId.DIRECTORY_SEPARATOR.$image['name'];
                            $jIterator++;
                        }
                        $purchaseOrderList[$iterator]['grn_generated'] = $alreadyGRNGenerated;
                    }else{
                        $purchaseOrderList[$iterator]['grn_generated'] = '';
                    }
                    $iterator++;
                }
            }
            $displayLength = 30;
            $start = ((int)$pageId) * $displayLength;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalOrderCount = count($purchaseOrderList);
            $remainingCount = $totalOrderCount - $totalSent;
            $data['purchase_order_list'] = array();
            for($iterator = $start,$jIterator = 0; $iterator < $totalOrderCount && $jIterator < $displayLength; $iterator++,$jIterator++){
                $data['purchase_order_list'][] = $purchaseOrderList[$iterator];
            }
            if($remainingCount > 0 ){
                $page_id = (string)($pageId + 1);
                $next_url = "/purchase/purchase-order/listing";
            }else{
                $next_url = "";
                $page_id = "";
            }
            $message = "Success";
            $status = 200;

        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Purchase Order Listing',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            $has_create_access = false;
            $next_url = "";
            $page_id = "";
            Log::critical(json_encode($data));
        }
        $response = [
            "has_create_access" => $has_create_access,
            'data' => $data,
            'message' => $message,
            "next_url" => $next_url,
            "page_id" => $page_id
        ];
        return response()->json($response,$status);
    }

    public function getPurchaseOrderDetail(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $purchaseOrder = PurchaseOrder::where('id',$request['purchase_order_id'])->first();
            $purchaseOrderList['purchase_order_id'] = $purchaseOrder['id'];
            $projectSite = $purchaseOrder->purchaseRequest->projectSite;
            $purchaseOrderList['purchase_order_format_id'] = $this->getPurchaseIDFormat('purchase-order',$projectSite['id'],$purchaseOrder['created_at'],$purchaseOrder['serial_no']);
            if($purchaseOrder->is_client_order == true){
                $purchaseOrderList['vendor_id'] = $purchaseOrder['client_id'];
                $purchaseOrderList['vendor_name'] = $purchaseOrder->client->company;
                $purchaseOrderList['vendor_mobile'] = $purchaseOrder->client->mobile;
                $purchaseOrderList['is_client_order'] = true;
            }else{
                $purchaseOrderList['vendor_id'] = $purchaseOrder['vendor_id'];
                $purchaseOrderList['vendor_name'] = $purchaseOrder->vendor->company;
                $purchaseOrderList['vendor_mobile'] = $purchaseOrder->vendor->mobile;
                $purchaseOrderList['is_client_order'] = false;
            }
            $purchaseOrderList['date'] = date($purchaseOrder['created_at']);
            $iterator = 0;
            foreach($purchaseOrder->purchaseOrderComponent as $key => $purchaseOrderComponent){
                $materialRequestComponent = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent;
                $purchaseOrderList['materials'][$iterator]['material_request_component_id'] = $materialRequestComponent->id;
                $purchaseOrderList['materials'][$iterator]['name'] = $materialRequestComponent->name;
                $purchaseOrderList['materials'][$iterator]['quantity'] = $purchaseOrderComponent['quantity'];
                $quantityConsumed = $purchaseOrderComponent->purchaseOrderTransactionComponent->sum('quantity');
                $purchaseOrderList['materials'][$iterator]['consumed_quantity'] = $quantityConsumed;
                $purchaseOrderList['materials'][$iterator]['rate_per_unit'] = $purchaseOrderComponent['rate_per_unit'];
                $purchaseOrderList['materials'][$iterator]['unit_id'] = $purchaseOrderComponent['unit_id'];
                $purchaseOrderList['materials'][$iterator]['unit_name'] = $purchaseOrderComponent->unit->name;
                $purchaseOrderList['materials'][$iterator]['expected_delivery_date'] = ($purchaseOrderComponent->expected_delivery_date == null) ? '' : $purchaseOrderComponent->expected_delivery_date;
                $purchaseOrderList['materials'][$iterator]['quotation_images'] = array();
                $purchaseOrderList['materials'][$iterator]['client_approval_images'] = array();
                $images = PurchaseOrderComponentImage::where('purchase_order_component_id',$purchaseOrderComponent['id'])->get();
                if(count($images) > 0){
                    $vendorQuotationImages = $images->where('is_vendor_approval',true);
                    $clientApprovalImages = $images->where('is_vendor_approval',false);
                    if(count($vendorQuotationImages) > 0){
                        $jIterator = 0;
                        foreach($vendorQuotationImages as $key1 => $image){
                            $sha1PurchaseOrderId = sha1($purchaseOrder['id']);
                            $sha1PurchaseOrderComponentId = sha1($purchaseOrderComponent['id']);
                            $imageUploadPath = env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'vendor_quotation_images'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderComponentId.DIRECTORY_SEPARATOR.$image['name'];
                            $purchaseOrderList['materials'][$iterator]['quotation_images'][$jIterator]['image_url'] = $imageUploadPath;
                            $jIterator++;
                        }
                    }

                    if(count($clientApprovalImages) > 0){
                        $jIterator = 0;
                        foreach($clientApprovalImages as $key1 => $image){
                            $sha1PurchaseOrderId = sha1($purchaseOrder['id']);
                            $sha1PurchaseOrderComponentId = sha1($purchaseOrderComponent['id']);
                            $imageUploadPath = env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'client_approval_images'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderComponentId.DIRECTORY_SEPARATOR.$image['name'];
                            $purchaseOrderList['materials'][$iterator]['client_approval_images'][$jIterator]['image_url'] = $imageUploadPath;
                            $jIterator++;
                        }
                    }

                }
                $iterator++;
            }

            $data = $purchaseOrderList;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get PO Detail',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function getPurchaseOrderMaterialListing(Request $request){
        try{
            $purchaseOrder = PurchaseOrder::where('id',$request['purchase_order_id'])->first();
            $iterator = 0;
            $materialList = array();
            foreach($purchaseOrder->purchaseOrderComponent as $key => $purchaseOrderComponent){
            $materialRequestComponent = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent;
            $materialList[$iterator]['purchase_order_component_id'] = $purchaseOrderComponent['id'];
            $materialList[$iterator]['material_request_component_id'] = $materialRequestComponent['id'];
            $materialList[$iterator]['material_component_name'] = $materialRequestComponent['name'];
            $quantityConsumed = $purchaseOrderComponent->purchaseOrderTransactionComponent->sum('quantity');
            $quantityUnused = $purchaseOrderComponent['quantity'] - $quantityConsumed;
            $materialList[$iterator]['material_component_remaining_quantity'] = (string)(((0.1 * ($purchaseOrderComponent['quantity'])) + $purchaseOrderComponent['quantity']) - $quantityConsumed);
            $materialList[$iterator]['material_component_units'] = array();
            $materialList[$iterator]['material_component_units'][0]['id'] = $materialRequestComponent['unit_id'];
            $materialList[$iterator]['material_component_units'][0]['name'] = $materialRequestComponent->unit->name;
            $images = PurchaseOrderComponentImage::where('purchase_order_component_id',$purchaseOrderComponent['id'])->get();
            if(count($images) > 0){
                $jIterator = 0;
                foreach($images as $key1 => $image){
                    $sha1PurchaseOrderId = sha1($purchaseOrder['id']);
                    $sha1PurchaseOrderComponentId = sha1($purchaseOrderComponent['id']);
                    $imageUploadPath = env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'vendor_quotation_images'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderComponentId.DIRECTORY_SEPARATOR.$image['name'];
                    $materialList[$iterator]['material_component_images'][$jIterator]['image_url'] = $imageUploadPath;
                    $jIterator++;
                }
            }

            $iterator++;
           }
           $data['material_list'] = $materialList;
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Purchase Order Material Listing',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function generateGRN(Request $request){
        try{
            $message = 'Success';
            $status = 200;

            $currentDate = Carbon::now();
            $purchaseOrderTransaction['purchase_order_id'] = $request['purchase_order_id'];
            $monthlyGrnGeneratedCount = GRNCount::where('month',$currentDate->month)->where('year',$currentDate->year)->pluck('count')->first();
            if($monthlyGrnGeneratedCount != null){
                $serialNumber = $monthlyGrnGeneratedCount + 1;
            }else{
                $serialNumber = 1;
            }
            $purchaseOrderTransaction['purchase_order_transaction_status_id'] = PurchaseOrderTransactionStatus::where('slug','grn-generated')->pluck('id')->first();
            $purchaseOrderTransaction['grn'] = "GRN".date('Ym').($serialNumber);
            $purchaseOrderTransaction['in_time'] = Carbon::now();
            $purchaseOrderTransactionData = PurchaseOrderTransaction::create($purchaseOrderTransaction);
            $user = Auth::user();
            $sha1UserId = sha1($user['id']);
            if($request->has('images')){
                $sha1PurchaseOrderId = sha1($request['purchase_order_id']);
                $sha1PurchaseOrderTransactionId = sha1($purchaseOrderTransactionData['id']);
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_TRANSACTION_TEMP_IMAGE_UPLOAD').DIRECTORY_SEPARATOR.$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill_transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderTransactionId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                        File::copy($tempUploadFile,$imageUploadNewPath);
                        PurchaseOrderTransactionImage::create(['name' => $imageName , 'purchase_order_transaction_id' => $purchaseOrderTransactionData['id'], 'is_pre_grn' => true]);
                    }
                }
            }

            if($request->has('images')){
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_TRANSACTION_TEMP_IMAGE_UPLOAD').DIRECTORY_SEPARATOR.$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        File::delete($tempUploadFile);
                    }
                }
            }

            if($monthlyGrnGeneratedCount != null) {
                GRNCount::where('month', $currentDate->month)->where('year', $currentDate->year)->update(['count' => $serialNumber]);
            }else{
                GRNCount::create(['month'=> $currentDate->month, 'year'=> $currentDate->year,'count' => $serialNumber]);
            }
            $data['grn'] = $purchaseOrderTransactionData['grn'];
        }catch(\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Generate GRN for Bill transaction',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function createPurchaseOrderTransaction(Request $request){
        try {
            $message = "Success";
            $status = 200;
            $purchaseOrderTransaction['vehicle_number'] = $request['vehicle_number'];
            $purchaseOrderTransaction['bill_amount'] = $request['bill_amount'];
            $purchaseOrderTransaction['remark'] = $request['remark'];
            $purchaseOrderTransaction['bill_number'] = $request['bill_number'];
            $purchaseOrderTransaction['out_time'] = Carbon::now();
            $purchaseOrderTransaction['purchase_order_transaction_status_id'] = PurchaseOrderTransactionStatus::where('slug','bill-pending')->pluck('id')->first();
            $purchaseOrderTransactionData = PurchaseOrderTransaction::where('grn',$request['grn'])->first();
            $purchaseOrderTransactionData->update($purchaseOrderTransaction);
            $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderTransactionData['purchase_order_id']);
            $projectInfo = $purchaseOrder->purchaseRequest->projectSite->project->name.' '.$purchaseOrder->purchaseRequest->projectSite->name;
            $user = Auth::user();
            $mainNotificationString = '4-'.$projectInfo.' '.$user->first_name.' '.$user->last_name.' Material Received. ';
            $sha1UserId = sha1($user['id']);
            $sha1PurchaseOrderId = sha1($purchaseOrderTransactionData['purchase_order_id']);
            $sha1PurchaseOrderTransactionId = sha1($purchaseOrderTransactionData['id']);
            if($request->has('images')){
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_TRANSACTION_TEMP_IMAGE_UPLOAD').DIRECTORY_SEPARATOR.$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill_transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderTransactionId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                        File::copy($tempUploadFile,$imageUploadNewPath);
                        PurchaseOrderTransactionImage::create(['name' => $imageName , 'purchase_order_transaction_id' => $purchaseOrderTransactionData['id'], 'is_pre_grn' => false]);
                    }
                }
            }

            if($request->has('images')){
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_TRANSACTION_TEMP_IMAGE_UPLOAD').DIRECTORY_SEPARATOR.$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        File::delete($tempUploadFile);
                    }
                }
            }
            foreach($request['item_list'] as $key => $material){
                $purchaseOrderTransactionComponent['purchase_order_component_id'] = $material['purchase_order_component_id'];
                $purchaseOrderTransactionComponent['purchase_order_transaction_id'] = $purchaseOrderTransactionData['id'];
                $purchaseOrderTransactionComponent['quantity'] = round($material['quantity'],3);
                $purchaseOrderTransactionComponent['unit_id'] = $material['unit_id'];
                $purchaseOrderTransactionComponentData = PurchaseOrderTransactionComponent::create($purchaseOrderTransactionComponent);
                $purchaseOrderComponent = PurchaseOrderComponent::where('id',$material['purchase_order_component_id'])->first();
                $materialRequestComponentVersion['material_request_component_id'] = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent->id;
                $materialRequestComponentVersion['purchase_order_transaction_status_id'] = $purchaseOrderTransactionData['purchase_order_transaction_status_id'];
                $materialRequestComponentVersion['user_id'] = $user['id'];
                $materialRequestComponentVersion['quantity'] = $purchaseOrderTransactionComponent['quantity'];
                $materialRequestComponentVersion['unit_id'] = $purchaseOrderTransactionComponent['unit_id'];
                $materialRequestComponentVersion['remark'] = $request['remark'];
                MaterialRequestComponentVersion::create($materialRequestComponentVersion);

                $materialRequestUserToken = User::join('material_requests','material_requests.on_behalf_of','=','users.id')
                    ->join('material_request_components','material_request_components.material_request_id','=','material_requests.id')
                    ->join('purchase_request_components','purchase_request_components.material_request_component_id','=','material_request_components.id')
                    ->join('purchase_order_components','purchase_order_components.purchase_request_component_id','=','purchase_request_components.id')
                    ->join('purchase_orders','purchase_orders.id','=','purchase_order_components.purchase_order_id')
                    ->where('purchase_orders.id', $purchaseOrder->id)
                    ->where('purchase_request_components.id', $purchaseOrderComponent->purchase_request_component_id)
                    ->select('users.web_fcm_token as web_fcm_function','users.mobile_fcm_token as mobile_fcm_function')
                    ->get()->toArray();
                $purchaseRequestApproveUserToken = User::join('material_request_component_history_table','material_request_component_history_table.user_id','=','users.id')
                                                    ->join('material_request_components','material_request_component_history_table.material_request_component_id','=','material_request_components.id')
                                                    ->join('purchase_request_components','purchase_request_components.material_request_component_id','=','material_request_components.id')
                                                    ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_component_history_table.component_status_id')
                                                    ->whereIn('purchase_request_component_statuses.slug',['p-r-manager-approved','p-r-admin-approved'])
                                                    ->where('purchase_request_components.id',$purchaseOrderComponent->purchase_request_component_id)
                                                    ->select('users.web_fcm_token as web_fcm_function','users.mobile_fcm_token as mobile_fcm_function')
                                                    ->get()->toArray();
                $webTokens = array_merge(array_column($materialRequestUserToken,'web_fcm_token'), array_column($purchaseRequestApproveUserToken,'web_fcm_token'));
                $mobileTokens = array_merge(array_column($materialRequestUserToken,'mobile_fcm_token'), array_column($purchaseRequestApproveUserToken,'mobile_fcm_token'));
                $notificationString = $mainNotificationString.' '.$purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent->name;
                $notificationString .= ' '.$purchaseOrderTransactionComponentData->quantity.' '.$purchaseOrderTransactionComponentData->unit->name;
                $this->sendPushNotification('Manisha Construction',$notificationString,$webTokens,$mobileTokens,'c-p-b');
                $materialRequestComponent = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent;
                $project_site_id = $materialRequestComponent->materialRequest->project_site_id;
                $materialComponentSlug = $materialRequestComponent->materialRequestComponentTypes->slug;
                $assetComponentTypeIds = MaterialRequestComponentTypes::whereIn('slug',['system-asset','new-asset'])->pluck('id')->toArray();
                if(in_array($purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent->component_type_id,$assetComponentTypeIds)){
                    $isMaterial = false;
                }else{
                    $isMaterial = true;
                }
                $alreadyPresent = InventoryComponent::where('name','ilike',$materialRequestComponent->name)->where('is_material',$isMaterial)->where('project_site_id',$project_site_id)->first();
                if($alreadyPresent != null){
                    $inventoryComponentId = $alreadyPresent['id'];
                }else{
                    if($materialComponentSlug == 'quotation-material' || $materialComponentSlug == 'new-material' || $materialComponentSlug == 'structure-material'){
                        $inventoryData['is_material'] = true;
                        $inventoryData['reference_id']  = Material::where('name','ilike',$materialRequestComponent->name)->pluck('id')->first();
                    }else{
                        $inventoryData['is_material'] = false;
                        $inventoryData['reference_id']  =  Asset::where('name','ilike',$materialRequestComponent->name)->pluck('id')->first();
                    }
                    $inventoryData['name'] = $materialRequestComponent->name;
                    $inventoryData['project_site_id'] = $project_site_id;
                    $inventoryData['purchase_order_component_id'] = $purchaseOrderComponent->id;
                    $inventoryData['opening_stock'] = 0;
                    $inventoryData['created_at'] = $inventoryData['updated_at'] = Carbon::now();
                    $inventoryComponent = InventoryComponent::create($inventoryData);
                    $inventoryComponentId = $inventoryComponent->id;
                }
                $transferData['inventory_component_id'] = $inventoryComponentId;
                $name = 'supplier';
                $type = 'IN';
                $transferData['quantity'] = $purchaseOrderTransactionComponentData->quantity;
                $transferData['unit_id'] = $purchaseOrderTransactionComponentData->unit_id;
                $transferData['date'] = $purchaseOrderTransactionData['created_at'];
                $transferData['in_time'] = $purchaseOrderTransactionData['in_time'];
                $transferData['out_time'] = $purchaseOrderTransactionData['out_time'];
                $transferData['vehicle_number'] = $purchaseOrderTransactionData['vehicle_number'];
                $transferData['bill_number'] = $purchaseOrderTransactionData['bill_number'];
                $transferData['bill_amount'] = $purchaseOrderTransactionData['bill_amount'];
                $transferData['remark'] = $purchaseOrderTransactionData['remark'];
                $transferData['source_name'] = $purchaseOrderComponent->purchaseOrder->vendor->name;
                $transferData['grn'] = $purchaseOrderTransactionData['grn'];
                $transferData['user_id'] = $user['id'];
                $createdTransferId = $this->create($transferData,$name,$type,'from-purchase');
                $transferData['images'] = array();
                $purchaseOrderTransactionImages = PurchaseOrderTransactionImage::where('purchase_order_transaction_id',$purchaseOrderTransactionData['id'])->get();
                if(count($purchaseOrderTransactionImages) > 0){
                    $sha1InventoryComponentId = sha1($inventoryComponentId);
                    $sha1InventoryTransferId = sha1($createdTransferId);
                    foreach ($purchaseOrderTransactionImages as $key1 => $image){
                        $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill_transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderTransactionId.DIRECTORY_SEPARATOR.$image['name'];
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('INVENTORY_TRANSFER_IMAGE_UPLOAD').$sha1InventoryComponentId.DIRECTORY_SEPARATOR.'transfers'.DIRECTORY_SEPARATOR.$sha1InventoryTransferId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$image['name'];
                        File::copy($tempUploadFile,$imageUploadNewPath);
                        InventoryComponentTransferImage::create(['name' => $image['name'],'inventory_component_transfer_id' => $createdTransferId]);
                    }
                }
            }
            $poClose = false;
            $purchaseOrderComponentIds = $purchaseOrder->purchaseOrderComponent->pluck('id');
            $purchaseOrderComponentQuantities = $purchaseOrder->purchaseOrderComponent->sum('quantity');
            $transactionQuantity = PurchaseOrderTransactionComponent::whereIn('purchase_order_component_id',$purchaseOrderComponentIds)->sum('quantity');
            if($transactionQuantity >= $purchaseOrderComponentQuantities){
                $poClose = true;
		$purchaseOrder->update([
                    'purchase_order_status_id' => PurchaseOrderStatus::where('slug','close')->pluck('id')->first()
                ]);
            }
            if($poClose && $purchaseOrder->purchaseOrderStatus->slug != 'close'){
                $mail_id = Vendor::where('id',$purchaseOrder['vendor_id'])->pluck('email')->first();
                $mailData = ['toMail' => $mail_id];
                $purchaseOrderComponent = $purchaseOrder->purchaseOrderComponent;
                Mail::send('purchase.purchase-order.email.purchase-order-close', ['purchaseOrder' => $purchaseOrder,'purchaseOrderComponent' => $purchaseOrderComponent], function($message) use ($mailData,$purchaseOrder){
                    $message->subject('PO '.$purchaseOrder->purchaseRequest->format_id.'has been closed');
                    $message->to($mailData['toMail']);
                    $message->from(env('MAIL_USERNAME'));
                });
                $purchaseOrder->update([
                    'purchase_order_status_id' => PurchaseOrderStatus::where('slug','close')->pluck('id')->first()
                ]);
            }
        } catch (\Exception $e) {
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Create Purchase Order Bill Transaction',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response, $status);
    }

    public function deleteUploadedImages($purchaseOrderId,$purchaseOrderBillId){
        $sha1PurchaseOrderId = sha1($purchaseOrderId);
        $sha1PurchaseOrderBillId = sha1($purchaseOrderBillId);
        $imageUploadPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill-transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderBillId;
        if(file_exists($imageUploadPath)){
            File::deleteDirectory($imageUploadPath,true);
            return true;
        }else{
            return false;
        }
    }

    public function getPurchaseOrderTransactionListing(Request $request){
        try{
            $pageId = $request->page;
            $message = 'Success';
            $status = 200;
            if($request->has('project_site_id')){
                $purchaseRequestIds = PurchaseRequests::where('project_site_id',$request['project_site_id'])->pluck('id');
                $purchaseOrderIds = PurchaseOrder::whereIn('purchase_request_id',$purchaseRequestIds)->pluck('id');
                if ($request->has('search_grn') && $request->search_grn != "" && $request->search_grn != null) {
                    $purchaseOrderTransactions = PurchaseOrderTransaction::whereIn('purchase_order_id',$purchaseOrderIds)
                        ->where('grn','ilike','%'.$request->search_grn.'%')
                        ->orderBy('created_at','desc')->get();
                } else {
                    if ($request->has('search_grn') && $request->search_grn != "" && $request->search_grn != null) {
                        $purchaseOrderTransactions = PurchaseOrderTransaction::whereIn('purchase_order_id',$purchaseOrderIds)
                            ->where('grn','ilike','%'.$request->search_grn.'%')
                            ->orderBy('created_at','desc')->get();
                    }else {
                        $purchaseOrderTransactions = PurchaseOrderTransaction::whereIn('purchase_order_id',$purchaseOrderIds)->orderBy('created_at','desc')->get();
                    }
                }
                
            }else{
                $purchaseOrderTransactions = PurchaseOrderTransaction::where('purchase_order_id',$request['purchase_order_id'])->orderBy('created_at','desc')->get();
            }
            $transactionData = array();
            $iterator = 0;
            foreach($purchaseOrderTransactions as $key => $purchaseOrderTransaction){
                $purchaseOrderTransactionComponents = $purchaseOrderTransaction->purchaseOrderTransactionComponent;
                $material_names = array();
                $jIterator = 0;
                $projectSiteID = $purchaseOrderTransaction->purchaseOrder->purchaseRequest->project_site_id;
                foreach ($purchaseOrderTransactionComponents as $key1 => $purchaseOrderTransactionComponent){
                    $purchaseOrderComponent = $purchaseOrderTransactionComponent->purchaseOrderComponent;
                    $purchaseRequestComponent = $purchaseOrderComponent->purchaseRequestComponent;
                    $transactionData[$iterator]['transaction_data'][$jIterator]['purchase_order_transaction_component_id'] = $purchaseOrderTransactionComponent['id'];
                    $transactionData[$iterator]['transaction_data'][$jIterator]['purchase_order_component_id'] = $purchaseOrderTransactionComponent['purchase_order_component_id'];
                    $transactionData[$iterator]['transaction_data'][$jIterator]['purchase_request_id'] = $purchaseRequestComponent->purchase_request_id;
                    $transactionData[$iterator]['transaction_data'][$jIterator]['purchase_request_format_id'] = $this->getPurchaseIDFormat('purchase-request',$projectSiteID,$purchaseRequestComponent['created_at'],$purchaseRequestComponent['serial_no']);
                    $transactionData[$iterator]['transaction_data'][$jIterator]['date'] = date('l, d F Y',strtotime($purchaseOrderTransactionComponent['created_at']));
                    $material_name = $purchaseRequestComponent->materialRequestComponent->name;
                    $transactionData[$iterator]['transaction_data'][$jIterator]['material_name'] = $material_name;
                    $transactionData[$iterator]['transaction_data'][$jIterator]['material_quantity'] = $purchaseOrderTransactionComponent['quantity'];
                    $transactionData[$iterator]['transaction_data'][$jIterator]['unit_id'] = $purchaseOrderTransactionComponent['unit_id'];
                    $transactionData[$iterator]['transaction_data'][$jIterator]['unit_name'] = $purchaseOrderTransactionComponent->unit->name;
                    $transactionData[$iterator]['transaction_data'][$jIterator]['vendor_name'] = $purchaseOrderComponent->purchaseOrder->vendor->name;
                    $material_name = $purchaseOrderTransactionComponent->purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent->name;
                    array_push($material_names,$material_name);
                    $jIterator++;
                }
                $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;
                $transactionData[$iterator]['purchase_order_transaction_id'] = $purchaseOrderTransaction['id'];
                $transactionData[$iterator]['grn'] = $purchaseOrderTransaction['grn'];
                $transactionData[$iterator]['purchase_order_id'] = $purchaseOrder['id'];
                $transactionData[$iterator]['purchase_order_format_id'] = $this->getPurchaseIDFormat('purchase-order',$projectSiteID,$purchaseOrder['created_at'],$purchaseOrder['serial_no']);
                $transactionData[$iterator]['material_name'] = implode(', ', $material_names);
                $transactionData[$iterator]['bill_number'] = $purchaseOrderTransaction['bill_number'];
                $transactionData[$iterator]['vehicle_number'] = $purchaseOrderTransaction['vehicle_number'];
                $transactionData[$iterator]['in_time'] = $purchaseOrderTransaction['in_time'];
                $transactionData[$iterator]['out_time'] = $purchaseOrderTransaction['out_time'];
                $transactionData[$iterator]['bill_amount'] = $purchaseOrderTransaction['bill_amount'];
                $transactionData[$iterator]['purchase_order_transaction_status_id'] = $purchaseOrderTransaction['purchase_order_transaction_status_id'];
                $transactionData[$iterator]['purchase_order_transaction_status'] = $purchaseOrderTransaction->purchaseOrderTransactionStatus->name;
                $transactionData[$iterator]['remark'] = $purchaseOrderTransaction['remark'];
                $purchaseOrderTransactionImages = PurchaseOrderTransactionImage::where('purchase_order_transaction_id',$purchaseOrderTransaction['id'])->get();
                if(count($purchaseOrderTransactionImages) > 0){
                    $kIterator = 0;
                    $sha1PurchaseOrderId = sha1($purchaseOrder['id']);
                    $sha1PurchaseOrderTransactionId = sha1($purchaseOrderTransaction['id']);
                    $imageUploadNewPath = env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill_transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderTransactionId;
                    foreach($purchaseOrderTransactionImages as $key3 => $purchaseOrderTransactionImage){
                        $transactionData[$iterator]['images'][$kIterator]['image_status'] = ($purchaseOrderTransactionImage['is_pre_grn'] == true) ? 'Pre-GRN' : 'Post-GRN';
                        $transactionData[$iterator]['images'][$kIterator]['image_url'] = $imageUploadNewPath.DIRECTORY_SEPARATOR.$purchaseOrderTransactionImage['name'];
                        $kIterator++;
                    }
                }

                $iterator++;
            }
            $displayLength = 30;
            $start = ((int)$pageId) * $displayLength;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalTransactionCount = count($transactionData);
            $remainingCount = $totalTransactionCount - $totalSent;
            $data['purchase_order_transaction_listing'] = array();
            for($iterator = $start,$jIterator = 0; $iterator < $totalTransactionCount && $jIterator < $displayLength; $iterator++,$jIterator++){
                $data['purchase_order_transaction_listing'][] = $transactionData[$iterator];
            }
            if($remainingCount > 0 ){
                $page_id = (string)($pageId + 1);
            }else{
                $page_id = "";
            }
        }catch (\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Get Purchase Order Bill Transaction listing',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            $page_id = "";
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message,
            "page_id" => $page_id
        ];
        return response()->json($response,$status);
    }

    /*public function getPurchaseOrderBillTransactionListing(Request $request){
        try{
            $pageId = $request->page;
            $message = 'Success';
            $status = 200;
            if($request->has('purchase_order_id')){
                $purchaseOrderComponentIDs = PurchaseOrderComponent::where('purchase_order_id',$request['purchase_order_id'])->pluck('id');
            }else{
                $purchaseRequestIds = PurchaseRequests::where('project_site_id',$request['project_site_id'])->pluck('id');
                $purchaseOrderIds = PurchaseOrder::whereIn('purchase_request_id',$purchaseRequestIds)->pluck('id');
                $purchaseOrderComponentIDs = PurchaseOrderComponent::whereIn('purchase_order_id',$purchaseOrderIds)->pluck('id');
            }
            $purchaseOrderBills = PurchaseOrderBill::whereIn('purchase_order_component_id',$purchaseOrderComponentIDs)->orderBy('created_at','desc')->get();
            $purchaseOrderBillData = $purchaseOrderBills->groupBy('grn');
            $purchaseOrderBillListing = array();
            $iterator = 0;
            foreach ($purchaseOrderBillData as $grn => $purchaseOrderBillArray){
                $jIterator = 0;
                $materialCount = count($purchaseOrderBillArray);
                $material_names = array();
                foreach ($purchaseOrderBillArray as $purchaseOrderBill){
                    $purchaseOrderComponent = $purchaseOrderBill->purchaseOrderComponent;
                    $purchaseRequestComponent = $purchaseOrderComponent->purchaseRequestComponent;
                    $projectSiteID = $purchaseRequestComponent->purchaseRequest->project_site_id;
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['id'] = $purchaseOrderBill['id'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['purchase_order_component_id'] = $purchaseOrderBill['purchase_order_component_id'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['purchase_request_id'] = $purchaseRequestComponent->purchase_request_id;
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['purchase_request_format_id'] = $this->getPurchaseIDFormat('purchase-request',$projectSiteID,$purchaseRequestComponent['created_at'],$purchaseRequestComponent['serial_no']);
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['purchase_order_id'] = $purchaseOrderComponent->purchase_order_id;
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['purchase_order_format_id'] = $this->getPurchaseIDFormat('purchase-order',$projectSiteID,$purchaseOrderComponent['created_at'],$purchaseOrderComponent['serial_no']);
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['purchase_order_bill_id'] = $purchaseOrderBill['id'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['date'] = date('l, d F Y',strtotime($purchaseOrderBill['created_at']));
                    $material_name = $purchaseRequestComponent->materialRequestComponent->name;
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['material_name'] = $material_name;
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['material_quantity'] = $purchaseOrderBill['quantity'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['unit_id'] = $purchaseOrderBill['unit_id'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['unit_name'] = $purchaseOrderBill->unit->name;
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['bill_number'] = $purchaseOrderBill['bill_number'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['vehicle_number'] = $purchaseOrderBill['vehicle_number'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['purchase_bill_grn'] = $purchaseOrderBill['grn'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['in_time'] = $purchaseOrderBill['in_time'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['out_time'] = $purchaseOrderBill['out_time'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['bill_amount'] = $purchaseOrderBill['bill_amount'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['remark'] = $purchaseOrderBill['remark'];
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['vendor_name'] = $purchaseOrderComponent->purchaseOrder->vendor->name;
                    if($purchaseOrderBill['purchase_order_bill_status_id'] != null){
                        $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['status'] = $purchaseOrderBill->purchaseOrderBillStatuses->name;
                    }else{
                        $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['status'] = '';
                    }
                    $purchaseOrderBillImages = PurchaseOrderBillImage::where('purchase_order_bill_id',$purchaseOrderBill['id'])->where('is_payment_image',false)->get();
                    $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['images'] = array();
                    if(count($purchaseOrderBillImages) > 0){
                        $kIterator = 0;
                        $sha1PurchaseOrderId = sha1($purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['purchase_order_id']);
                        $sha1PurchaseOrderBillId = sha1($purchaseOrderBill['id']);
                        $imageUploadPath = env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill-transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderBillId;
                        foreach($purchaseOrderBillImages as $index => $images){
                            $purchaseOrderBillListing[$iterator]['bill_data'][$jIterator]['images'][$kIterator]['image_url'] = $imageUploadPath.DIRECTORY_SEPARATOR.$images['name'];
                            $kIterator++;
                        }
                    }
                    $grn = $purchaseOrderBill['grn'];
                    array_push($material_names,$material_name);
                    $jIterator++;
                }
                if($materialCount == $jIterator){
                    $purchaseOrderBillListing[$iterator]['material_name'] = implode(', ', $material_names);
                    $purchaseOrderBillListing[$iterator]['grn'] = $grn;
                }
                $iterator++;
            }
            $displayLength = 30;
            $start = ((int)$pageId) * $displayLength;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalBillCount = count($purchaseOrderBillListing);
            $remainingCount = $totalBillCount - $totalSent;
            $data['purchase_order_bill_listing'] = array();
            for($iterator = $start,$jIterator = 0; $iterator < $totalSent && $jIterator < $totalBillCount; $iterator++,$jIterator++){
                $data['purchase_order_bill_listing'][] = $purchaseOrderBillListing[$iterator];
            }
            if($remainingCount > 0 ){
                $page_id = (string)($pageId + 1);
                $next_url = "/purchase/purchase-order/bill-listing";
            }else{
                $next_url = "";
                $page_id = "";
            }
        }catch (\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Get Purchase Order Bill Transaction listing',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            $next_url = "";
            $page_id = "";
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message,
            "next_url" => $next_url,
            "page_id" => $page_id
        ];
        return response()->json($response,$status);
    }*/

    public function changeStatus(Request $request){
        try{
            $purchaseOrder = PurchaseOrder::where('id',$request['purchase_order_id'])->first();
            $purchaseOrder->update([
                'purchase_order_status_id' => PurchaseOrderStatus::where('slug',$request['change_status_to_slug'])->pluck('id')->first()
            ]);
            if($request['change_status_to_slug'] == 'close'){
                $mail_id = Vendor::where('id',$purchaseOrder['vendor_id'])->pluck('email')->first();
                $mailData = ['toMail' => $mail_id];
                $purchaseOrderComponent = $purchaseOrder->purchaseOrderComponent;
                Mail::send('purchase.purchase-order.email.purchase-order-close', ['purchaseOrder' => $purchaseOrder,'purchaseOrderComponent' => $purchaseOrderComponent], function($message) use ($mailData,$purchaseOrder){
                    $message->subject('PO '.$purchaseOrder->purchaseRequest->format_id.'has been closed');
                    $message->to($mailData['toMail']);
                    $message->from(env('MAIL_USERNAME'));
                });
            }
            $message = 'Status changed successfully';
            $status = 200;
        }catch(\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Change Purchase Order status',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
        ];
        return response()->json($response,$status);
    }

    public function authenticatePOClose(Request $request){
        try{
            $password = $request->password;
            if(Hash::check($password, env('CLOSE_PURCHASE_ORDER_PASSWORD'))){
                $status = 200;
                $message = 'Authentication successful !!';
            }else{
                $status = 401;
                $message = 'You are not authorised to close this purchase order.';
            }
        }catch (\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Authenticate Purchase Order Close',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
        ];
        return response()->json($response,$status);
    }
}
