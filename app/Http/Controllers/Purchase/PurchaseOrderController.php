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
use App\Http\Controllers\CustomTraits\PurchaseTrait;
use App\InventoryComponent;
use App\InventoryComponentTransferImage;
use App\InventoryTransferTypes;
use App\Material;
use App\MaterialRequestComponents;
use App\MaterialRequestComponentTypes;
use App\PaymentType;
use App\PurchaseOrder;
use App\PurchaseOrderBill;
use App\PurchaseOrderBillImage;
use App\PurchaseOrderBillPayment;
use App\PurchaseOrderBillStatus;
use App\PurchaseOrderComponent;
use App\PurchaseOrderComponentImage;
use App\PurchaseOrderTransaction;
use App\PurchaseOrderTransactionComponent;
use App\PurchaseOrderTransactionImage;
use App\PurchaseOrderTransactionStatus;
use App\PurchaseRequestComponents;
use App\PurchaseRequests;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class PurchaseOrderController extends BaseController{
use PurchaseTrait;
use InventoryTrait;
    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()){
            $this->user = Auth::user();
        }
    }

    public function getPurchaseOrderListing(Request $request){
        try{
            $pageId = $request->page;
            if($request->has('purchase_request_id')){
                $purchaseOrderDetail = PurchaseOrder::where('purchase_request_id',$request['purchase_request_id'])->orderBy('created_at','desc')->get();
            }else{
                $purchaseRequestIds = PurchaseRequests::where('project_site_id',$request['project_site_id'])->pluck('id');
                $purchaseOrderDetail = PurchaseOrder::whereIn('purchase_request_id',$purchaseRequestIds)->orderBy('created_at','desc')->get();
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
                    $purchaseOrderList[$iterator]['vendor_id'] = $purchaseOrder['vendor_id'];
                    $purchaseOrderList[$iterator]['vendor_name'] = $purchaseOrder->vendor->name;
                    $purchaseOrderList[$iterator]['client_name'] = $project->client->company;
                    $purchaseOrderList[$iterator]['project'] = $project->name;
                    $purchaseOrderList[$iterator]['date'] = date($purchaseOrder['created_at']);
                    $purchaseRequestComponentIds = $purchaseOrder->purchaseOrderComponent->pluck('purchase_request_component_id');
                    $material_names = MaterialRequestComponents::join('purchase_request_components','material_request_components.id','=','purchase_request_components.material_request_component_id')
                        ->whereIn('purchase_request_components.id',$purchaseRequestComponentIds)
                        ->distinct('material_request_components.name')->select('material_request_components.name')->take(5)->get();
                    $purchaseOrderList[$iterator]['materials'] = $material_names->implode('name', ', ');
                    $purchaseOrderList[$iterator]['status'] = ($purchaseOrder['is_approved'] == true) ? 'Approved' : 'Disapproved';
                    $alreadyGRNGenerated = PurchaseOrderTransaction::where('purchase_order_id',$purchaseOrder['id'])->whereNull('bill_number')->pluck('grn')->last();
                    if(count($alreadyGRNGenerated) > 0){
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
            for($iterator = $start,$jIterator = 0; $iterator < $totalSent && $jIterator < $totalOrderCount; $iterator++,$jIterator++){
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
    }

    public function getPurchaseOrderDetail(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $purchaseOrder = PurchaseOrder::where('id',$request['purchase_order_id'])->first();
            $purchaseOrderList['purchase_order_id'] = $purchaseOrder['id'];
            $projectSite = $purchaseOrder->purchaseRequest->projectSite;
            $purchaseOrderList['purchase_order_format_id'] = $this->getPurchaseIDFormat('purchase-order',$projectSite['id'],$purchaseOrder['created_at'],$purchaseOrder['serial_no']);
            $purchaseOrderList['vendor_id'] = $purchaseOrder['vendor_id'];
            $vendor = $purchaseOrder->vendor;
            $purchaseOrderList['vendor_name'] = $vendor->name;
            $purchaseOrderList['vendor_mobile'] = $vendor->mobile;
            $purchaseOrderList['date'] = date($purchaseOrder['created_at']);
            $iterator = 0;
            foreach($purchaseOrder->purchaseOrderComponent as $key => $purchaseOrderComponent){
                $materialRequestComponent = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent;
                $purchaseOrderList['materials'][$iterator]['material_request_component_id'] = $materialRequestComponent->id;
                $purchaseOrderList['materials'][$iterator]['name'] = $materialRequestComponent->name;
                $purchaseOrderList['materials'][$iterator]['quantity'] = $purchaseOrderComponent['quantity'];
                $purchaseOrderList['materials'][$iterator]['unit_id'] = $purchaseOrderComponent['unit_id'];
                $purchaseOrderList['materials'][$iterator]['unit_name'] = $purchaseOrderComponent->unit->name;
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

    public function createPurchaseOrderBillTransaction(Request $request)
    {
        try {
            $message = "Success";
            $status = 200;
            $purchaseOrderTransaction['vehicle_number'] = $request['vehicle_number'];
            /*$purchaseOrderTransaction['bill_amount'] = $request['vehicle_number'];*/
            $purchaseOrderTransaction['remark'] = $request['remark'];
            $purchaseOrderTransaction['bill_number'] = $request['bill_number'];
            $purchaseOrderTransaction['out_time'] = Carbon::now();
            $purchaseOrderTransaction['purchase_order_transaction_status_id'] = PurchaseOrderTransactionStatus::where('slug','bill-pending')->pluck('id')->first();
            PurchaseOrderTransaction::where('grn',$request['grn'])->update($purchaseOrderTransaction);
            $purchaseOrderTransactionData = PurchaseOrderTransaction::where('grn',$request['grn'])->first();
            $user = Auth::user();
            $sha1UserId = sha1($user['id']);
            if($request->has('images')){
                $sha1PurchaseOrderId = sha1($purchaseOrderTransactionData['purchase_order_id']);
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
                $purchaseOrderTransactionComponent['quantity'] = $material['quantity'];
                $purchaseOrderTransactionComponent['unit_id'] = $material['unit_id'];
                $purchaseOrderTransactionComponentData = PurchaseOrderTransactionComponent::create($purchaseOrderTransactionComponent);
                $purchaseOrderComponent = PurchaseOrderComponent::where('id',$material['purchase_order_component_id'])->first();
                $materialRequestComponent = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent;
                $project_site_id = $materialRequestComponent->materialRequest->project_site_id;
                $materialComponentSlug = $materialRequestComponent->materialRequestComponentTypes->slug;
                $alreadyPresent = InventoryComponent::where('name','ilike',$materialRequestComponent->name)->where('project_site_id',$project_site_id)->first();
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
                    /*$transferData['bill_amount'] = $purchaseOrderTransactionData['bill_amount'];
                    $transferData['remark'] = $purchaseOrderTransactionData['remark'];*/
                    $transferData['source_name'] = $purchaseOrderComponent->purchaseOrder->vendor->name;
                    $transferData['grn'] = $purchaseOrderTransactionData['grn'];
                    $transferData['user_id'] = $user['id'];
                    $createdTransferId = $this->create($transferData,$name,$type,'from-purchase');
                    $transferData['images'] = array();
                    /*if(count($purchaseOrderBillImages) > 0){
                        $sha1InventoryComponentId = sha1($inventoryComponentId);
                        $sha1InventoryTransferId = sha1($createdTransferId);
                        $purchaseOrderId = $purchaseOrderBill->purchaseOrderComponent->purchaseOrder['id'];
                        $sha1PurchaseOrderId = sha1($purchaseOrderId);
                        $sha1PurchaseOrderBillId = sha1($purchaseOrderBill['id']);
                        foreach ($purchaseOrderBillImages as $key => $image){
                            $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill-transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderBillId.DIRECTORY_SEPARATOR.$image['name'];
                            $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('INVENTORY_TRANSFER_IMAGE_UPLOAD').$sha1InventoryComponentId.DIRECTORY_SEPARATOR.'transfers'.DIRECTORY_SEPARATOR.$sha1InventoryTransferId;
                            if(!file_exists($imageUploadNewPath)) {
                                File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                            }
                            $imageUploadNewPath .= DIRECTORY_SEPARATOR.$image['name'];
                            File::copy($tempUploadFile,$imageUploadNewPath);
                            InventoryComponentTransferImage::create(['name' => $image['name'],'inventory_component_transfer_id' => $createdTransferId]);
                        }
                    }*/
                }
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

    /*public function createPurchaseOrderBillTransaction(Request $request){
        try{
            $user = Auth::user();
            $sha1UserId = sha1($user['id']);
            $updatePurchaseOrderBill = $request->except('type','token','grn');
            switch($request['type']){
                case 'upload-bill' :
                    $updatePurchaseOrderBill['purchase_order_bill_status_id'] = PurchaseOrderBillStatus::where('slug','bill-pending')->pluck('id')->first();;
                    break;

                case 'create-amendment' :
                    $updatePurchaseOrderBill['purchase_order_bill_status_id'] = PurchaseOrderBillStatus::where('slug','amendment-pending')->pluck('id')->first();
                    break;
            }
            $purchaseOrderBills = PurchaseOrderBill::where('grn',$request['grn'])->get();
            foreach($purchaseOrderBills as $index => $purchaseOrderBill){
                $purchaseOrderBill->update($updatePurchaseOrderBill);
                if($request->has('images')){
                    $purchaseOrderId = $purchaseOrderBill->purchaseOrderComponent->purchase_order_id;
                    $sha1PurchaseOrderId = sha1($purchaseOrderId);
                    $sha1PurchaseOrderBillId = sha1($purchaseOrderBill['id']);
                    foreach($request['images'] as $key1 => $imageName){
                        $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_BILL_POST_GRN_TRANSACTION_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                        if(File::exists($tempUploadFile)){
                            $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill-transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderBillId;
                            if(!file_exists($imageUploadNewPath)) {
                                File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                            }
                            $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                            File::copy($tempUploadFile,$imageUploadNewPath);
                            PurchaseOrderBillImage::create(['name' => $imageName , 'purchase_order_bill_id' => $purchaseOrderBill['id'], 'is_payment_image' => false]);
                        }
                    }
                }
                $purchaseOrderComponent = PurchaseOrderComponent::where('id',$purchaseOrderBill['purchase_order_component_id'])->first();
                $materialRequestComponent = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent;
                $project_site_id = $materialRequestComponent->materialRequest->project_site_id;
                $materialComponentSlug = $materialRequestComponent->materialRequestComponentTypes->slug;
                $alreadyPresent = InventoryComponent::where('name','ilike',$materialRequestComponent->name)->where('project_site_id',$project_site_id)->first();
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
                $transferData['quantity'] = $purchaseOrderBill->quantity;
                $transferData['unit_id'] = $purchaseOrderBill->unit_id;
                $transferData['date'] = $purchaseOrderBill['created_at'];
                $transferData['in_time'] = $purchaseOrderBill['in_time'];
                $transferData['out_time'] = $purchaseOrderBill['out_time'];
                $transferData['vehicle_number'] = $purchaseOrderBill['vehicle_number'];
                $transferData['bill_number'] = $purchaseOrderBill['bill_number'];
                $transferData['bill_amount'] = $purchaseOrderBill['bill_amount'];
                $transferData['remark'] = $purchaseOrderBill['remark'];
                $transferData['source_name'] = $purchaseOrderComponent->purchaseOrder->vendor->name;
                $transferData['grn'] = $purchaseOrderBill['grn'];
                $transferData['user_id'] = $user['id'];
                $createdTransferId = $this->create($transferData,$name,$type,'from-purchase');
                $transferData['images'] = array();
                $purchaseOrderBillImages = PurchaseOrderBillImage::where('purchase_order_bill_id',$purchaseOrderBill['id'])->where('is_payment_image', (boolean)false)->get();
                if(count($purchaseOrderBillImages) > 0){
                    $sha1InventoryComponentId = sha1($inventoryComponentId);
                    $sha1InventoryTransferId = sha1($createdTransferId);
                    $purchaseOrderId = $purchaseOrderBill->purchaseOrderComponent->purchaseOrder['id'];
                    $sha1PurchaseOrderId = sha1($purchaseOrderId);
                    $sha1PurchaseOrderBillId = sha1($purchaseOrderBill['id']);
                    foreach ($purchaseOrderBillImages as $key => $image){
                        $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill-transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderBillId.DIRECTORY_SEPARATOR.$image['name'];
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
            if($request->has('images')){
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_BILL_POST_GRN_TRANSACTION_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        File::delete($tempUploadFile);
                    }
                }
            }
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
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
        return response()->json($response,$status);
    }*/

    public function editPurchaseOrderBillTransaction(Request $request){
        try{
            $purchaseOrderBill = $request->except('purchase_order_bill_id','token','images');
            PurchaseOrderBill::where('id',$request['purchase_order_bill_id'])->update($purchaseOrderBill);
            $purchaseOrderBillData = PurchaseOrderBill::where('id',$request['purchase_order_bill_id'])->first();
            $purchaseOrderId = $purchaseOrderBillData->purchaseOrderComponent->purchaseOrder['id'];
            if($request->has('images')){
                $imagesAlreadyExist = PurchaseOrderBillImage::where('purchase_order_bill_id',$request['purchase_order_bill_id'])->get();
                if(count($imagesAlreadyExist) > 0){
                        $imageDelete = $this->deleteUploadedImages($purchaseOrderId,$request['purchase_order_bill_id']);
                        if($imageDelete == true){
                            PurchaseOrderBillImage::where('purchase_order_bill_id',$request['purchase_order_bill_id'])->delete();
                            $message = "Edited Successfully";
                        }else{
                            $message = "Unable to delete images";
                        }
                }
                $user = Auth::user();
                $sha1UserId = sha1($user['id']);
                $sha1PurchaseOrderId = sha1($purchaseOrderId);
                $sha1PurchaseOrderBillId = sha1($request['purchase_order_bill_id']);
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_BILL_TRANSACTION_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill-transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderBillId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                        File::move($tempUploadFile,$imageUploadNewPath);
                        PurchaseOrderBillImage::create(['name' => $imageName , 'purchase_order_bill_id' => $request['purchase_order_bill_id'], 'is_payment_image' => false]);
                    }
                }
            }else{
                $message = "Edited Successfully";
            }
            $status = 200;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Edit Purchase Order Bill Transaction',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response,$status);
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

    public function getPurchaseOrderBillTransactionListing(Request $request){
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
    }

    public function createBillPayment(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $purchaseOrderBillPayment['purchase_order_bill_id'] = $request['purchase_order_bill_id'];
            $purchaseOrderBillPayment['payment_id'] = PaymentType::where('slug',$request['payment_slug'])->pluck('id')->first();
            $purchaseOrderBillPayment['amount'] = $request['amount'];
            $purchaseOrderBillPayment['reference_number'] = $request['reference_number'];
            $purchaseOrderBillPayment['remark'] = $request['remark'];
            $purchaseOrderBillPayment['created_at'] = $purchaseOrderBillPayment['updated_at'] = Carbon::now();
            $purchaseOrderBillPaymentId = PurchaseOrderBillPayment::insertGetId($purchaseOrderBillPayment);
            PurchaseOrderBill::where('id',$request['purchase_order_bill_id'])->update(['purchase_order_bill_status_id' => PurchaseOrderBillStatus::where('slug','amendment-pending')->pluck('id')->first()]);
            $purchaseOrderBill = PurchaseOrderBill::where('id',$request['purchase_order_bill_id'])->first();
            $purchaseOrderId = $purchaseOrderBill->purchaseOrderComponent->purchaseOrder->id;
            if($request->has('images')){
                $user = Auth::user();
                $sha1UserId = sha1($user['id']);
                $sha1PurchaseOrderBillPaymentId = sha1($purchaseOrderBillPaymentId);
                $sha1PurchaseOrderId = sha1($purchaseOrderId);
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_BILL_PAYMENT_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill-payment'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderBillPaymentId;
                        Log::info($imageUploadNewPath);
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                        File::move($tempUploadFile,$imageUploadNewPath);
                        PurchaseOrderBillImage::create([
                            'purchase_order_bill_id' => $purchaseOrderBillPaymentId ,
                            'name' => $imageName,
                            'is_payment_image' => true
                        ]);
                    }
                }
            }
        }catch (\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Create Bill Payment',
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