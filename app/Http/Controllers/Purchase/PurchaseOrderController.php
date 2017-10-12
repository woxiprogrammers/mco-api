<?php
    /**
     * Created by Harsha.
     * Date: 5/10/17
     * Time: 11:18 AM
     */

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\CustomTraits\PurchaseTrait;
use App\MaterialRequestComponents;
use App\PaymentType;
use App\PurchaseOrder;
use App\PurchaseOrderBill;
use App\PurchaseOrderBillImage;
use App\PurchaseOrderBillPayment;
use App\PurchaseOrderComponent;
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
    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()){
            $this->user = Auth::user();
        }
    }

    public function getPurchaseOrderListing(Request $request){
        try{
            $pageId = $request->page;
            $purchaseOrderDetail = PurchaseOrder::where('purchase_request_id',$request['purchase_request_id'])->get();
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
                    $iterator++;
                }
            }
            $displayLength = 10;
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
            $materialList[$iterator]['material_component_images'][0]['image_id'] = 1;
            $materialList[$iterator]['material_component_images'][0]['image_url'] = '/assets/global/img/logo.jpg';
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

    public function createPurchaseOrderBillTransaction(Request $request){
        try{
            $purchaseOrderBill = $request->except('type','token','images');
            switch($request['type']){
                case 'upload_bill' :
                    $purchaseOrderBill['is_amendment'] = false;
                    break;

                case 'create-amendment' :
                    $purchaseOrderBill['is_amendment'] = true;
                    break;
            }
            $purchaseOrderBill['is_paid'] = false;
            $currentTimeStamp = Carbon::now();
            $serialNoCount = PurchaseOrderBill::whereMonth('created_at',date_format($currentTimeStamp,'m'))->whereYear('created_at',date_format($currentTimeStamp,'Y'))->count();
            $purchaseOrderBill['grn'] = "GRN".date_format($currentTimeStamp,'Y').date_format($currentTimeStamp,'m').($serialNoCount + 1);
            $purchaseOrderBill['created_at'] = $currentTimeStamp;
            $purchaseOrderBill['updated_at'] = $currentTimeStamp;
            $purchaseOrderBillId = PurchaseOrderBill::insertGetId($purchaseOrderBill);
            $purchaseOrderBillData = PurchaseOrderBill::where('id',$purchaseOrderBillId)->first();
            $purchaseOrderId = $purchaseOrderBillData->purchaseOrderComponent->purchaseOrder['id'];
            if($request->has('images')){
                $user = Auth::user();
                $sha1UserId = sha1($user['id']);
                $sha1PurchaseOrderId = sha1($purchaseOrderId);
                $sha1PurchaseOrderBillId = sha1($purchaseOrderBillId);
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_BILL_TRANSACTION_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').$sha1PurchaseOrderId.DIRECTORY_SEPARATOR.'bill-transaction'.DIRECTORY_SEPARATOR.$sha1PurchaseOrderBillId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                        File::move($tempUploadFile,$imageUploadNewPath);
                        PurchaseOrderBillImage::create(['name' => $imageName , 'purchase_order_bill_id' => $purchaseOrderBillId, 'is_payment_image' => false]);
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
    }

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
            $purchaseOrderComponentIDs = PurchaseOrderComponent::where('purchase_order_id',$request['purchase_order_id'])->pluck('id');
            $purchaseOrderBillData = PurchaseOrderBill::whereIn('purchase_order_component_id',$purchaseOrderComponentIDs)->get();
            $purchaseOrderBillListing = array();
            $iterator = 0;
            foreach($purchaseOrderBillData as $key => $purchaseOrderBill){
                $purchaseOrderComponent = $purchaseOrderBill->purchaseOrderComponent;
                $purchaseRequestComponent = $purchaseOrderComponent->purchaseRequestComponent;
                $projectSiteID = $purchaseRequestComponent->purchaseRequest->project_site_id;
                $purchaseOrderBillListing[$iterator]['purchase_request_id'] = $purchaseRequestComponent->purchase_request_id;
                $purchaseOrderBillListing[$iterator]['purchase_request_format_id'] = $this->getPurchaseIDFormat('purchase-request',$projectSiteID,$purchaseRequestComponent['created_at'],$purchaseRequestComponent['serial_no']);
                $purchaseOrderBillListing[$iterator]['purchase_order_id'] = $purchaseOrderComponent->purchase_order_id;
                $purchaseOrderBillListing[$iterator]['purchase_order_format_id'] = $this->getPurchaseIDFormat('purchase-order',$projectSiteID,$purchaseOrderComponent['created_at'],$purchaseOrderComponent['serial_no']);
                $purchaseOrderBillListing[$iterator]['purchase_order_bill_id'] = $purchaseOrderBill['id'];
                $purchaseOrderBillListing[$iterator]['date'] = date('l, d F Y',strtotime($purchaseOrderBill['created_at']));
                $purchaseOrderBillListing[$iterator]['material_name'] = $purchaseRequestComponent->materialRequestComponent->name;
                $purchaseOrderBillListing[$iterator]['material_quantity'] = $purchaseOrderBill['quantity'];
                $purchaseOrderBillListing[$iterator]['unit_id'] = $purchaseOrderBill['unit_id'];
                $purchaseOrderBillListing[$iterator]['unit_name'] = $purchaseOrderBill->unit->name;
                $purchaseOrderBillListing[$iterator]['bill_number'] = $purchaseOrderBill['bill_number'];
                $purchaseOrderBillListing[$iterator]['vehicle_number'] = $purchaseOrderBill['vehicle_number'];
                $purchaseOrderBillListing[$iterator]['purchase_bill_grn'] = $purchaseOrderBill['grn'];
                $purchaseOrderBillListing[$iterator]['in_time'] = $purchaseOrderBill['in_time'];
                $purchaseOrderBillListing[$iterator]['out_time'] = $purchaseOrderBill['out_time'];
                $purchaseOrderBillListing[$iterator]['bill_amount'] = $purchaseOrderBill['bill_amount'];
                $purchaseOrderBillListing[$iterator]['vendor_name'] = $purchaseOrderComponent->purchaseOrder->vendor->name;
                if($purchaseOrderComponent['is_amendment'] == true){
                    $purchaseOrderBillListing[$iterator]['status'] = 'Amendment Pending';
                }else{
                    $purchaseOrderBillListing[$iterator]['status'] = ($purchaseOrderComponent['is_paid'] == true) ? 'Bill Paid' : 'Bill Pending';
                }
                $iterator++;
            }
            $displayLength = 10;
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
            $purchaseOrderBillPayment['created_at'] = $purchaseOrderBillPayment['updated_at'] = Carbon::now();
            $purchaseOrderBillPaymentId = PurchaseOrderBillPayment::insertGetId($purchaseOrderBillPayment);
            PurchaseOrderBillPayment::where('id',$request['purchase_order_bill_id'])->update(['is_paid' => true, 'is_amendment' => true]);
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
            Log::crtical(json_encode($data));
        }
        $response = [
            'message' => $message,
        ];
        return response()->json($response,$status);
    }
}