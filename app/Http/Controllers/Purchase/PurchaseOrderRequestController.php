<?php
    /**
     * Created by Harsha.
     * Date: 23/1/18
     * Time: 10:14 AM
     */

namespace App\Http\Controllers\Purchase;
use App\Asset;
use App\AssetType;
use App\CategoryMaterialRelation;
use App\Client;
use App\Helper\MaterialProductHelper;
use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\Http\Controllers\CustomTraits\PurchaseTrait;
use App\Material;
use App\MaterialRequestComponentTypes;
use App\MaterialVersion;
use App\PurchaseOrder;
use App\PurchaseOrderComponent;
use App\PurchaseOrderComponentImage;
use App\PurchaseOrderRequest;
use App\PurchaseOrderRequestComponent;
use App\PurchaseOrderStatus;
use App\PurchaseRequestComponents;
use App\PurchaseRequestComponentStatuses;
use App\PurchaseRequestComponentVendorMailInfo;
use App\PurchaseRequestComponentVendorRelation;
use App\PurchaseRequests;
use App\Unit;
use App\User;
use App\Vendor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Lumen\Routing\Controller as BaseController;

class PurchaseOrderRequestController extends BaseController
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if (!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function getPurchaseOrderRequestListing(Request $request)
    {
        try {
            $purchaseRequestIds = PurchaseRequests::where('project_site_id', $request['project_site_id'])->pluck('id');
            $purchaseOrderRequests = PurchaseOrderRequest::whereIn('purchase_request_id', $purchaseRequestIds)->whereMonth('created_at', $request['month'])->whereYear('created_at', $request['year'])->where('ready_to_approve', true)->orderBy('created_at','desc')->get();
            $iterator = 0;
            $purchaseOrderRequestList = array();
            foreach ($purchaseOrderRequests as $key => $purchaseOrderRequest) {
                $totalComponentCount = count($purchaseOrderRequest->purchaseOrderRequestComponents->toArray());
                $processedComponentCount = PurchaseOrderRequestComponent::where('purchase_order_request_id', $purchaseOrderRequest->id)
                                                            ->whereNotNull('is_approved')
                                                            ->count();
                if($totalComponentCount > $processedComponentCount){
                    $purchaseOrderRequestList[$iterator]['purchase_order_request_id'] = $purchaseOrderRequest['id'];
                    $purchaseOrderRequestList[$iterator]['purchase_request_id'] = $purchaseOrderRequest['purchase_request_id'];
                    $purchaseOrderRequestList[$iterator]['purchase_request_format_id'] = $purchaseOrderRequest->purchaseRequest->format_id;
                    $componentNamesArray = PurchaseOrderRequestComponent::join('purchase_request_component_vendor_relation', 'purchase_request_component_vendor_relation.id', '=', 'purchase_order_request_components.purchase_request_component_vendor_relation_id')
                        ->join('purchase_request_components', 'purchase_request_components.id', '=', 'purchase_request_component_vendor_relation.purchase_request_component_id')
                        ->join('material_request_components', 'purchase_request_components.material_request_component_id', '=', 'material_request_components.id')
                        ->where('purchase_order_request_components.purchase_order_request_id', $purchaseOrderRequest->id)
                        ->distinct('material_request_components.name')
                        ->pluck('material_request_components.name')
                        ->toArray();
                    $purchaseOrderRequestList[$iterator]['component_names'] = implode(', ', $componentNamesArray);
                    $purchaseOrderRequestList[$iterator]['user_name'] = $purchaseOrderRequest->user->first_name . ' ' . $purchaseOrderRequest->user->last_name;
                    $purchaseOrderRequestList[$iterator]['date'] = date('l, d F Y', strtotime($purchaseOrderRequest['created_at']));
                    $purchaseOrderRequestList[$iterator]['purchase_order_done'] = false;
                    $iterator++;
                }
            }
            $status = 200;
            $message = "Success";
            $data['purchase_order_request_list'] = $purchaseOrderRequestList;
        } catch (\Exception $e) {
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
        return response()->json($response, $status);
    }

    public function getPurchaseOrderRequestDetail(Request $request)
    {
        try {
            $purchaseOrderRequestComponents = array();
            $purchaseOrderRequestComponentData = PurchaseOrderRequestComponent::where('purchase_order_request_id', $request['purchase_order_request_id'])->get();
            foreach ($purchaseOrderRequestComponentData as $purchaseOrderRequestComponent) {
                $purchaseRequestComponentId = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->purchase_request_component_id;
                if(!array_key_exists($purchaseRequestComponentId,$purchaseOrderRequestComponents)){
                    $materialRequestComponent = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->purchaseRequestComponent->materialRequestComponent;
                    $purchaseOrderRequestComponents[$purchaseRequestComponentId]['material_request_component_id'] = $materialRequestComponent['id'];
                    $purchaseOrderRequestComponents[$purchaseRequestComponentId]['name'] = ucwords($materialRequestComponent->name);
                    $purchaseOrderRequestComponents[$purchaseRequestComponentId]['quantity'] = $purchaseOrderRequestComponent->quantity;
                    $purchaseOrderRequestComponents[$purchaseRequestComponentId]['unit'] = $purchaseOrderRequestComponent->unit->name;
                    $purchaseRequestComponentVendorRelationIds = PurchaseRequestComponentVendorRelation::where('purchase_request_component_id', $purchaseRequestComponentId)->pluck('id')->toArray();
                    $totalPurchaseOrderRequestComponentIds = PurchaseOrderRequestComponent::where('purchase_order_request_id', $request['purchase_order_request_id'])
                                                                        ->whereIn('purchase_request_component_vendor_relation_id', $purchaseRequestComponentVendorRelationIds)
                                                                        ->pluck('purchase_order_request_components.id')->toArray();
                    $processedPurchaseOrderRequestCompCount = PurchaseOrderRequestComponent::whereIn('id', $totalPurchaseOrderRequestComponentIds)
                                                                        ->whereNull('is_approved')
                                                                        ->count('id');
                    if($processedPurchaseOrderRequestCompCount == 0){
                        $purchaseOrderRequestComponents[$purchaseRequestComponentId]['is_approved'] = true;
                    }else{
                        $purchaseOrderRequestComponents[$purchaseRequestComponentId]['is_approved'] = false;
                    }
                }
                $rateWithTax = $purchaseOrderRequestComponent->rate_per_unit;
                $rateWithTax += ($purchaseOrderRequestComponent->rate_per_unit * ($purchaseOrderRequestComponent->cgst_percentage / 100));
                $rateWithTax += ($purchaseOrderRequestComponent->rate_per_unit * ($purchaseOrderRequestComponent->sgst_percentage / 100));
                $rateWithTax += ($purchaseOrderRequestComponent->rate_per_unit * ($purchaseOrderRequestComponent->igst_percentage / 100));
                if($purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->vendor != null){
                    $vendorName = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->vendor->company;
                    $vendorId = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->vendor_id;
                }else{
                    $vendorName = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->client->company;
                    $vendorId = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->client_id;
                }
                $images = array();
                $pdf = array();
                foreach($purchaseOrderRequestComponent->purchaseOrderRequestComponentImages as $purchaseOrderRequestComponentImage){
                    $mainDirectoryPath = env('PURCHASE_ORDER_REQUEST_IMAGE_UPLOAD').DIRECTORY_SEPARATOR.sha1($request['purchase_order_request_id']);
                    $componentDirectoryName = sha1($purchaseOrderRequestComponentImage->purchase_order_request_component_id);
                    if($purchaseOrderRequestComponentImage['is_vendor_approval'] == true){
                        $path = $mainDirectoryPath.DIRECTORY_SEPARATOR.'vendor_quotation_images'.DIRECTORY_SEPARATOR.$componentDirectoryName.DIRECTORY_SEPARATOR.$purchaseOrderRequestComponentImage->name;
                    }else{
                        $path = $mainDirectoryPath.DIRECTORY_SEPARATOR.'client_approval_images'.DIRECTORY_SEPARATOR.$componentDirectoryName.DIRECTORY_SEPARATOR.$purchaseOrderRequestComponentImage->name;
                    }
                    $ext = pathinfo($purchaseOrderRequestComponentImage['name'],PATHINFO_EXTENSION);
                    if($ext == 'pdf' || $ext == 'PDF'){
                        $pdf[] = [
                            'path' => $path
                        ];
                    }else{
                        $images[] = [
                            'path' => $path
                        ];
                    }
                }
                $purchaseOrderRequestComponents[$purchaseRequestComponentId]['vendor_relations'][] = [
                    'purchase_order_request_component_id' => $purchaseOrderRequestComponent->id,
                    'component_vendor_relation_id' => $purchaseOrderRequestComponent->purchase_request_component_vendor_relation_id,
                    'vendor_name' => $vendorName,
                    'vendor_id' => $vendorId,
                    'rate_without_tax' => (string)$purchaseOrderRequestComponent->rate_per_unit,
                    'rate_with_tax' => (string)$rateWithTax,
                    'gst' => (string)($rateWithTax - $purchaseOrderRequestComponent->rate_per_unit),
                    'total_with_tax' => (string)($rateWithTax * $purchaseOrderRequestComponents[$purchaseRequestComponentId]['quantity']),
                    'expected_delivery_date' => $purchaseOrderRequestComponent->expected_delivery_date,
                    'transportation_amount' => $purchaseOrderRequestComponent->transportation_amount,
                    'transportation_cgst_percentage' =>  $purchaseOrderRequestComponent->transportation_cgst_percentage,
                    'transportation_cgst_amount' =>  ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_cgst_percentage) /100,
                    'transportation_sgst_percentage' =>  $purchaseOrderRequestComponent->transportation_sgst_percentage,
                    'transportation_sgst_amount' =>  ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_sgst_percentage) / 100,
                    'transportation_igst_percentage' => $purchaseOrderRequestComponent->transportation_igst_percentage,
                    'transportation_igst_amount' => ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_igst_percentage) / 100,
                    'transportation_gst' => (string)(($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_cgst_percentage) /100
                                                + ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_igst_percentage) / 100
                                                + ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_sgst_percentage) / 100),

                    'total_transportation_amount' => $purchaseOrderRequestComponent->transportation_amount
                                                        + ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_cgst_percentage) /100
                                                        + ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_sgst_percentage) / 100
                                                        + ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_igst_percentage) / 100,
                    'images' => $images,
                    'pdf' => $pdf
                ];
            }
            $data['purchase_order_request_list'] = array_values($purchaseOrderRequestComponents);
            $data['pdf_thumbnail_url'] = '/assets/global/img/pdf.jpg';
            $status = 200;
            $message = "Success";
        } catch (\Exception $e) {
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
        return response()->json($response, $status);
    }

    use PurchaseTrait;
    use NotificationTrait;

    public function changeStatus(Request $request){
        try{
            $purchaseOrderData = [
                'vendors' => array(),
                'clients' => array()
            ];
            $user = Auth::user();
            $purchaseOrderCount = PurchaseOrder::whereDate('created_at', Carbon::now())->count();
            $projectSiteId = PurchaseRequests::join('purchase_order_requests','purchase_requests.id','=','purchase_order_requests.purchase_request_id')
                ->join('purchase_order_request_components','purchase_order_request_components.purchase_order_request_id','=','purchase_order_requests.id')
                ->where('purchase_order_request_components.id',$request['purchase_order_request_components'][0]['id'])
                ->pluck('purchase_requests.project_site_id')
                ->first();
            $newAssetTypeId = MaterialRequestComponentTypes::where('slug','new-asset')->pluck('id')->first();
            $newMaterialTypeId = MaterialRequestComponentTypes::where('slug','new-material')->pluck('id')->first();
            $purchaseRequestFormatId = null;
            foreach ($request['purchase_order_request_components'] as $key => $purchase_order_request_component) {
                if ($purchase_order_request_component['is_approved'] == 'true') {
                    /*Approve and create purchase order*/
                    $purchaseOrderRequestComponent = PurchaseOrderRequestComponent::findOrFail($purchase_order_request_component['id']);
                    if($purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->is_client == true){
                        /*client supplied PO*/
                        $clientId =$purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->client_id;
                        if(!array_key_exists($clientId, $purchaseOrderData['clients'])){
                            $purchaseOrderCount++;
                            $purchaseOrderFormatID = $this->getPurchaseIDFormat('purchase-order',$projectSiteId,Carbon::now(),$purchaseOrderCount);
                            $purchaseOrderData['clients'][$clientId] = array();
                            $purchaseOrderData['clients'][$clientId]['purchase_order_data'] = [
                                'user_id' => Auth::user()->id,
                                'client_id' => $clientId,
                                'is_approved' => true,
                                'purchase_request_id' => $purchaseOrderRequestComponent->purchaseOrderRequest->purchase_request_id,
                                'purchase_order_status_id' => PurchaseOrderStatus::where('slug','open')->pluck('id')->first(),
                                'is_client_order' => true,
                                'purchase_order_request_id' => $purchaseOrderRequestComponent->purchaseOrderRequest->id,
                                'format_id' => $purchaseOrderFormatID,
                                'serial_no' => $purchaseOrderCount,
                                'is_email_sent' => false
                            ];
                            $purchaseOrderData['clients'][$clientId]['component_data'] = array();
                        }
                        $purchaseOrderComponentData = PurchaseOrderRequestComponent::where('id', $purchaseOrderRequestComponent->id)
                            ->select('id as purchase_order_request_component_id','rate_per_unit','gst','hsn_code','expected_delivery_date','remark','credited_days',
                                'quantity','unit_id','cgst_percentage','sgst_percentage','igst_percentage','cgst_amount',
                                'sgst_amount','igst_amount','total')
                            ->first()->toArray();
                        if($purchaseOrderComponentData['rate_per_unit'] == null || $purchaseOrderComponentData['rate_per_unit'] == ''){
                            $purchaseOrderComponentData['rate_per_unit'] = 0;
                        }
                        $purchaseOrderComponentData['purchase_request_component_id'] = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->purchase_request_component_id;
                        $purchaseOrderData['clients'][$clientId]['component_data'][] = $purchaseOrderComponentData;
                    }else{
                        /*Vendor PO*/
                        $vendorId =$purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->vendor_id;
                        if(!array_key_exists($vendorId, $purchaseOrderData['vendors'])){
                            $purchaseOrderCount++;
                            $purchaseOrderFormatID = $this->getPurchaseIDFormat('purchase-order',$projectSiteId,Carbon::now(),$purchaseOrderCount);
                            $purchaseOrderData['vendors'][$vendorId] = array();
                            $purchaseOrderData['vendors'][$vendorId]['purchase_order_data'] = [
                                'user_id' => Auth::user()->id,
                                'vendor_id' => $vendorId,
                                'is_approved' => true,
                                'purchase_request_id' => $purchaseOrderRequestComponent->purchaseOrderRequest->purchase_request_id,
                                'purchase_order_status_id' => PurchaseOrderStatus::where('slug','open')->pluck('id')->first(),
                                'is_client_order' => false,
                                'purchase_order_request_id' => $purchaseOrderRequestComponent->purchaseOrderRequest->id,
                                'format_id' => $purchaseOrderFormatID,
                                'serial_no' => $purchaseOrderCount,
                                'is_email_sent' => false
                            ];
                            $purchaseOrderData['vendors'][$vendorId]['component_data'] = array();
                        }
                        $purchaseOrderComponentData = PurchaseOrderRequestComponent::where('id', $purchaseOrderRequestComponent->id)
                            ->select('id as purchase_order_request_component_id','rate_per_unit','gst','hsn_code','expected_delivery_date','remark','credited_days',
                                'quantity','unit_id','cgst_percentage','sgst_percentage','igst_percentage','cgst_amount',
                                'sgst_amount','igst_amount','total')
                            ->first()->toArray();
                        if($purchaseOrderComponentData['rate_per_unit'] == null || $purchaseOrderComponentData['rate_per_unit'] == ''){
                            $purchaseOrderComponentData['rate_per_unit'] = 0;
                        }
                        $purchaseOrderComponentData['purchase_request_component_id'] = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->purchase_request_component_id;
                        $purchaseOrderData['vendors'][$vendorId]['component_data'][] = $purchaseOrderComponentData;
                    }

                    $purchaseRequestComponentId = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->purchase_request_component_id;
                    $disapprovePurchaseOrderRequestComponentIds = PurchaseOrderRequestComponent::join('purchase_request_component_vendor_relation','purchase_request_component_vendor_relation.id','=','purchase_order_request_components.purchase_request_component_vendor_relation_id')
                                                                        ->where('purchase_request_component_vendor_relation.purchase_request_component_id',$purchaseRequestComponentId)
                                                                        ->where('purchase_order_request_components.id','!=',$purchaseOrderRequestComponent['id'])
                                                                        ->where('purchase_order_request_components.purchase_order_request_id',$purchaseOrderRequestComponent['purchase_order_request_id'])
                                                                        ->pluck('purchase_order_request_components.id')->toArray();
                    if(count($disapprovePurchaseOrderRequestComponentIds) > 0){
                        PurchaseOrderRequestComponent::whereIn('id',$disapprovePurchaseOrderRequestComponentIds)
                                ->update([
                                    'is_approved' => false,
                                    'approve_disapprove_by_user' => $user['id']
                                ]);
                    }
                }
            }
            foreach ($purchaseOrderData as $slug => $purchaseOrderDatum){
                foreach ($purchaseOrderDatum as $vendorId => $purchaseOrderDataArray){
                    $purchaseOrder = PurchaseOrder::create($purchaseOrderDataArray['purchase_order_data']);
                    if(!isset($purchaseRequestFormatId)){
                        $purchaseRequestFormatId = $purchaseOrder->purchaseRequest->format_id;
                    }
                    foreach ($purchaseOrderDataArray['component_data'] as $purchaseOrderComponentData){
                        $purchaseOrderComponentData['purchase_order_id'] = $purchaseOrder->id;
                        $purchaseOrderRequestComponent = PurchaseOrderRequestComponent::findOrFail($purchaseOrderComponentData['purchase_order_request_component_id']);
                        $purchaseOrderComponent = PurchaseOrderComponent::create($purchaseOrderComponentData);
                        $purchaseOrderRequestComponent->update(['is_approved' => true]);
                        $componentTypeId = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent->component_type_id;
                        if($newMaterialTypeId == $componentTypeId){
                            $materialName = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent->name;
                            $isMaterialExists = Material::where('name','ilike',$materialName)->first();
                            if($isMaterialExists == null){
                                $materialData = [
                                    'name' => $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent->name,
                                    'is_active' => true,
                                    'rate_per_unit' => $purchaseOrderComponent->rate_per_unit,
                                    'unit_id' => $purchaseOrderComponent->unit_id,
                                    'hsn_code' => $purchaseOrderComponent->hsn_code,
                                    'gst' => $purchaseOrderComponent->gst
                                ];
                                $material = Material::create($materialData);
                                $categoryMaterialData = [
                                    'material_id' => $material->id,
                                    'category_id' => $purchaseOrderRequestComponent->category_id
                                ];
                                CategoryMaterialRelation::create($categoryMaterialData);
                                $materialVersionData = [
                                    'material_id' => $material->id,
                                    'rate_per_unit' => $material->rate_per_unit,
                                    'unit_id' => $material->unit_id
                                ];
                                MaterialVersion::create($materialVersionData);
                            }
                        }elseif ($newAssetTypeId == $componentTypeId){
                            $assetName = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent->name;
                            $is_present = Asset::where('name','ilike',$assetName)->first();
                            if($is_present == null){
                                $asset_type = AssetType::where('slug','other')->pluck('id')->first();
                                $categoryAssetData = array();
                                $categoryAssetData['asset_types_id'] = $asset_type;
                                $categoryAssetData['name'] = $assetName;
                                $categoryAssetData['quantity'] = 1;
                                $categoryAssetData['is_fuel_dependent'] = false;
                                Asset::create($categoryAssetData);
                            }
                        }

                        if(count($purchaseOrderRequestComponent->purchaseOrderRequestComponentImages) > 0){
                            $purchaseOrderMainDirectoryName = sha1($purchaseOrderComponent['purchase_order_id']);
                            $purchaseOrderComponentDirectoryName = sha1($purchaseOrderComponent['id']);
                            $mainDirectoryName = sha1($purchaseOrder->purchase_order_request_id);
                            $componentDirectoryName = sha1($purchaseOrderRequestComponent->id);
                            foreach ($purchaseOrderRequestComponent->purchaseOrderRequestComponentImages as $purchaseOrderRequestComponentImage){
                                if($purchaseOrderRequestComponentImage->is_vendor_approval == true){
                                    $toUploadPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').DIRECTORY_SEPARATOR.$purchaseOrderMainDirectoryName.DIRECTORY_SEPARATOR.'vendor_quotation_images'.DIRECTORY_SEPARATOR.$purchaseOrderComponentDirectoryName;
                                    $fromUploadPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_REQUEST_IMAGE_UPLOAD').DIRECTORY_SEPARATOR.$mainDirectoryName.DIRECTORY_SEPARATOR.'vendor_quotation_images'.DIRECTORY_SEPARATOR.$componentDirectoryName;
                                }else{
                                    $toUploadPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_IMAGE_UPLOAD').DIRECTORY_SEPARATOR.$purchaseOrderMainDirectoryName.DIRECTORY_SEPARATOR.'client_approval_images'.DIRECTORY_SEPARATOR.$purchaseOrderComponentDirectoryName;
                                    $fromUploadPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_REQUEST_IMAGE_UPLOAD').DIRECTORY_SEPARATOR.$mainDirectoryName.DIRECTORY_SEPARATOR.'client_approval_images'.DIRECTORY_SEPARATOR.$componentDirectoryName;
                                }
                                if (!file_exists($toUploadPath)) {
                                    File::makeDirectory($toUploadPath, $mode = 0777, true, true);
                                }
                                $fromUploadPath = $fromUploadPath.DIRECTORY_SEPARATOR.$purchaseOrderRequestComponentImage->name;
                                $toUploadPath = $toUploadPath.DIRECTORY_SEPARATOR.$purchaseOrderRequestComponentImage->name;
                                if(file_exists($fromUploadPath)){
                                    $imageData = [
                                        'purchase_order_component_id' => $purchaseOrderComponent['id'] ,
                                        'name' => $purchaseOrderRequestComponentImage->name,
                                        'caption' => $purchaseOrderRequestComponentImage->caption,
                                        'is_vendor_approval' => $purchaseOrderRequestComponentImage->is_vendor_approval
                                    ];
                                    File::move($fromUploadPath, $toUploadPath);
                                    PurchaseOrderComponentImage::create($imageData);
                                }
                            }
                        }
                    }
                    $webTokens = [$purchaseOrder->purchaseRequest->onBehalfOfUser->web_fcm_token];
                    $mobileTokens = [$purchaseOrder->purchaseRequest->onBehalfOfUser->mobile_fcm_token];
                    $purchaseRequestComponentIds = array_column(($purchaseOrder->purchaseOrderComponent->toArray()),'purchase_request_component_id');
                    $materialRequestUserToken = User::join('material_requests','material_requests.on_behalf_of','=','users.id')
                        ->join('material_request_components','material_request_components.material_request_id','=','material_requests.id')
                        ->join('purchase_request_components','purchase_request_components.material_request_component_id','=','material_request_components.id')
                        ->join('purchase_order_components','purchase_order_components.purchase_request_component_id','=','purchase_request_components.id')
                        ->join('purchase_orders','purchase_orders.id','=','purchase_order_components.purchase_order_id')
                        ->where('purchase_orders.id', $purchaseOrder->id)
                        ->whereIn('purchase_request_components.id', $purchaseRequestComponentIds)
                        ->select('users.web_fcm_token as web_fcm_function','users.mobile_fcm_token as mobile_fcm_function')
                        ->get()->toArray();

                    $materialRequestComponentIds = PurchaseRequestComponents::whereIn('id',$purchaseRequestComponentIds)->pluck('material_request_component_id')->toArray();
                    $purchaseRequestApproveStatusesId = PurchaseRequestComponentStatuses::whereIn('slug',['p-r-manager-approved','p-r-admin-approved'])->pluck('id');
                    $purchaseRequestApproveUserToken = User::join('material_request_component_history_table','material_request_component_history_table.user_id','=','users.id')
                        ->whereIn('material_request_component_history_table.material_request_component_id',$materialRequestComponentIds)
                        ->whereIn('material_request_component_history_table.component_status_id',$purchaseRequestApproveStatusesId)
                        ->select('users.web_fcm_token as web_fcm_function','users.mobile_fcm_token as mobile_fcm_function')
                        ->get()->toArray();
                    $materialRequestUserToken = array_merge($materialRequestUserToken,$purchaseRequestApproveUserToken);
                    $webTokens = array_merge($webTokens, array_column($materialRequestUserToken,'web_fcm_function'));
                    $mobileTokens = array_merge($mobileTokens, array_column($materialRequestUserToken,'mobile_fcm_function'));
                    $notificationString = '3 -'.$purchaseOrder->purchaseRequest->projectSite->project->name.' '.$purchaseOrder->purchaseRequest->projectSite->name;
                    $notificationString .= ' '.$user['first_name'].' '.$user['last_name'].'Purchase Order Created.';
                    $notificationString .= 'PO number: '.$purchaseOrder->format_id;
                    $this->sendPushNotification('Manisha Construction',$notificationString,array_unique($webTokens),array_unique($mobileTokens),'c-p-o');
                }
            }

            $message = "Component status changed successfully";
            $status = 200;
        }catch (\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Purchase Order Request change status',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response, $status);
    }

    public function disapproveComponent(Request $request){
        try{
            $user = Auth::user();
            $status = 200;
            $purchaseOrderRequestComponentId = PurchaseOrderRequestComponent::join('purchase_request_component_vendor_relation','purchase_request_component_vendor_relation.id','=','purchase_order_request_components.purchase_request_component_vendor_relation_id')
                ->join('purchase_request_components','purchase_request_components.id','=','purchase_request_component_vendor_relation.purchase_request_component_id')
                ->where('purchase_request_components.material_request_component_id', $request->material_request_component_id)
                ->pluck('purchase_order_request_components.id');
            PurchaseOrderRequestComponent::whereIn('id', $purchaseOrderRequestComponentId)->update(['is_approved' => false,'approve_disapprove_by_user' => $user['id']]);
            $message = "Material / Asset remove successfully !!";
        }catch (\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Disapprove Purchase Order Request component',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response, $status);
    }

}
