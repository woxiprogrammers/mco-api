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
use App\PurchaseRequestComponentVendorMailInfo;
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
            $purchaseOrderRequests = PurchaseOrderRequest::whereIn('purchase_request_id', $purchaseRequestIds)->whereMonth('created_at', $request['month'])->whereYear('created_at', $request['year'])->orderBy('created_at','desc')->get();
            $iterator = 0;
            $purchaseOrderRequestList = array();
            $allPurchaseOrderComponentIds = PurchaseOrderComponent::pluck('purchase_order_request_component_id')->toArray();
            foreach ($purchaseOrderRequests as $key => $purchaseOrderRequest) {
                $purchaseOrderRequestComponentIds = array_column(($purchaseOrderRequest->purchaseOrderRequestComponents->toArray()),'id');
                $arrayDiff = array_diff($purchaseOrderRequestComponentIds,$allPurchaseOrderComponentIds);
                if(count($arrayDiff) > 0){
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
                    $purchaseOrderCount = PurchaseOrder::where('purchase_order_request_id', $purchaseOrderRequest['id'])->count();
                    $purchaseOrderRequestList[$iterator]['purchase_order_done'] = ($purchaseOrderCount > 0) ? true : false;
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
                    $purchaseOrderRequestComponents[$purchaseRequestComponentId]['is_approved'] = ($purchaseOrderRequestComponent->is_approved == true) ? true : false;
                }
                $rateWithTax = $purchaseOrderRequestComponent->rate_per_unit;
                $rateWithTax += ($purchaseOrderRequestComponent->rate_per_unit * ($purchaseOrderRequestComponent->cgst_percentage / 100));
                $rateWithTax += ($purchaseOrderRequestComponent->rate_per_unit * ($purchaseOrderRequestComponent->sgst_percentage / 100));
                $rateWithTax += ($purchaseOrderRequestComponent->rate_per_unit * ($purchaseOrderRequestComponent->igst_percentage / 100));
                $purchaseOrderRequestComponents[$purchaseRequestComponentId]['vendor_relations'][] = [
                    'purchase_order_request_component_id' => $purchaseOrderRequestComponent->id,
                    'component_vendor_relation_id' => $purchaseOrderRequestComponent->purchase_request_component_vendor_relation_id,
                    'vendor_name' => $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->vendor->company,
                    'vendor_id' => $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->vendor_id,
                    'rate_without_tax' => (string)$purchaseOrderRequestComponent->rate_per_unit,
                    'rate_with_tax' => (string)$rateWithTax,
                    'total_with_tax' => (string)($rateWithTax * $purchaseOrderRequestComponents[$purchaseRequestComponentId]['quantity']),
                    'expected_delivery_date' => $purchaseOrderRequestComponent->expected_delivery_date,
                    'transportation_amount' => $purchaseOrderRequestComponent->transportation_amount,
                    'transportation_cgst_percentage' =>  $purchaseOrderRequestComponent->transportation_cgst_percentage,
                    'transportation_cgst_amount' =>  ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_cgst_percentage) /100,
                    'transportation_sgst_percentage' =>  $purchaseOrderRequestComponent->transportation_sgst_percentage,
                    'transportation_sgst_amount' =>  ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_sgst_percentage) / 100,
                    'transportation_igst_percentage' => $purchaseOrderRequestComponent->transportation_igst_percentage,
                    'transportation_igst_amount' => ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_igst_percentage) / 100,
                    'total_transportation_amount' => $purchaseOrderRequestComponent->transportation_amount
                                                        + ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_cgst_percentage) /100
                                                        + ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_sgst_percentage) / 100
                                                        + ($purchaseOrderRequestComponent->transportation_amount * $purchaseOrderRequestComponent->transportation_igst_percentage) / 100
                ];
            }
            $data['purchase_order_request_list'] = array_values($purchaseOrderRequestComponents);
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
    public function changeStatus(Request $request)
    {
        try{
            $purchaseOrderData = [
                'vendors' => array(),
                'clients' => array()
            ];
            $user = Auth::user();
            $assetComponentTypeIds = MaterialRequestComponentTypes::whereIn('slug',['new-material','system-asset'])->pluck('id')->toArray();
            $purchaseOrderCount = PurchaseOrder::whereDate('created_at', Carbon::now())->count();
            $projectSiteId = PurchaseRequests::join('purchase_order_requests','purchase_requests.id','=','purchase_order_requests.purchase_request_id')
                                ->join('purchase_order_request_components','purchase_order_request_components.purchase_order_request_id','=','purchase_order_requests.id')
                                ->where('purchase_order_request_components.id',$request['purchase_order_request_components'][0]['id'])
                                ->pluck('purchase_requests.project_site_id')
                                ->first();
            $newAssetTypeId = MaterialRequestComponentTypes::where('slug','new-asset')->pluck('id')->first();
            $newMaterialTypeId = MaterialRequestComponentTypes::where('slug','new-material')->pluck('id')->first();
            foreach ($request['purchase_order_request_components'] as $key => $purchase_order_request_component) {
                if ($purchase_order_request_component['is_approved'] == true) {
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
                                'serial_no' => $purchaseOrderCount
                            ];
                            $purchaseOrderData['clients'][$clientId]['component_data'] = array();
                        }
                        $purchaseOrderComponentData = PurchaseOrderRequestComponent::where('id', $purchaseOrderRequestComponent->id)
                            ->select('id as purchase_order_request_component_id','rate_per_unit','gst','hsn_code','expected_delivery_date','remark','credited_days',
                                'quantity','unit_id','cgst_percentage','sgst_percentage','igst_percentage','cgst_amount',
                                'sgst_amount','igst_amount','total')
                            ->first()->toArray();
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
                                'serial_no' => $purchaseOrderCount
                            ];
                            $purchaseOrderData['vendors'][$vendorId]['component_data'] = array();
                        }
                        $purchaseOrderComponentData = PurchaseOrderRequestComponent::where('id', $purchaseOrderRequestComponent->id)
                            ->select('id as purchase_order_request_component_id','rate_per_unit','gst','hsn_code','expected_delivery_date','remark','credited_days',
                                'quantity','unit_id','cgst_percentage','sgst_percentage','igst_percentage','cgst_amount',
                                'sgst_amount','igst_amount','total')
                            ->first()->toArray();
                        $purchaseOrderComponentData['purchase_request_component_id'] = $purchaseOrderRequestComponent->purchaseRequestComponentVendorRelation->purchase_request_component_id;
                        $purchaseOrderData['vendors'][$vendorId]['component_data'][] = $purchaseOrderComponentData;
                    }

                }else{
                    /*disapprove*/

                    PurchaseOrderRequestComponent::where('id', $purchase_order_request_component['id'])
                        ->update(['is_approved' => $purchase_order_request_component['is_approved']]);
                }
            }
            foreach ($purchaseOrderData as $slug => $purchaseOrderDatum){
                foreach ($purchaseOrderDatum as $vendorId => $purchaseOrderDataArray){
                    $purchaseOrderRequest = PurchaseOrderRequest::findOrFail($purchaseOrderDataArray['purchase_order_data']['purchase_order_request_id']);
                    $purchaseOrder = PurchaseOrder::create($purchaseOrderDataArray['purchase_order_data']);
                    if($slug == 'clients'){
                        $vendorInfo = Client::findOrFail($purchaseOrder->client_id)->toArray();
                    }else{
                        $vendorInfo = Vendor::findOrFail($purchaseOrder->vendor_id)->toArray();
                    }
                    $vendorInfo['materials'] = array();
                    $iterator = 0;
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
                            $assetName = $purchaseOrderRequestComponent->purchaseRequestComponent->materialRequestComponent->name;
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
                        $webTokens = array_merge($webTokens, array_column($materialRequestUserToken,'web_fcm_token'));
                        $mobileTokens = array_merge($mobileTokens, array_column($materialRequestUserToken,'mobile_fcm_token'));
                        $notificationString = '3 -'.$purchaseOrder->purchaseRequest->projectSite->project->name.' '.$purchaseOrder->purchaseRequest->projectSite->name;
                        $notificationString .= ' '.$user['first_name'].' '.$user['last_name'].'Purchase Order Created.';
                        $notificationString .= 'PO number: '.$purchaseOrder->format_id;
                        $this->sendPushNotification('Manisha Construction',$notificationString,$webTokens,$mobileTokens,'c-p-o');
                        $vendorInfo['materials'][$iterator]['item_name'] = $purchaseOrderComponent->purchaseRequestComponent->materialRequestComponent->name;
                        $vendorInfo['materials'][$iterator]['quantity'] = $purchaseOrderComponent['quantity'];
                        $vendorInfo['materials'][$iterator]['unit'] = Unit::where('id',$purchaseOrderComponent['unit_id'])->pluck('name')->first();
                        $vendorInfo['materials'][$iterator]['hsn_code'] = $purchaseOrderComponent['hsn_code'];
                        $vendorInfo['materials'][$iterator]['rate'] = $purchaseOrderComponent['rate_per_unit'];
                        $vendorInfo['materials'][$iterator]['subtotal'] = MaterialProductHelper::customRound(($purchaseOrderComponent['quantity'] * $purchaseOrderComponent['rate_per_unit']));
                        if($purchaseOrderComponent['cgst_percentage'] == null || $purchaseOrderComponent['cgst_percentage'] == ''){
                            $vendorInfo['materials'][$iterator]['cgst_percentage'] = 0;
                        }else{
                            $vendorInfo['materials'][$iterator]['cgst_percentage'] = $purchaseOrderComponent['cgst_percentage'];
                        }
                        $vendorInfo['materials'][$iterator]['cgst_amount'] = $vendorInfo['materials'][$iterator]['subtotal'] * ($vendorInfo['materials'][$iterator]['cgst_percentage']/100);
                        if($purchaseOrderComponent['sgst_percentage'] == null || $purchaseOrderComponent['sgst_percentage'] == ''){
                            $vendorInfo['materials'][$iterator]['sgst_percentage'] = 0;
                        }else{
                            $vendorInfo['materials'][$iterator]['sgst_percentage'] = $purchaseOrderComponent['sgst_percentage'];
                        }
                        $vendorInfo['materials'][$iterator]['sgst_amount'] = $vendorInfo['materials'][$iterator]['subtotal'] * ($vendorInfo['materials'][$iterator]['sgst_percentage']/100);
                        if($purchaseOrderComponent['igst_percentage'] == null || $purchaseOrderComponent['igst_percentage'] == ''){
                            $vendorInfo['materials'][$iterator]['igst_percentage'] = 0;
                        }else{
                            $vendorInfo['materials'][$iterator]['igst_percentage'] = $purchaseOrderComponent['igst_percentage'];
                        }
                        $vendorInfo['materials'][$iterator]['igst_amount'] = $vendorInfo['materials'][$iterator]['subtotal'] * ($vendorInfo['materials'][$iterator]['igst_percentage']/100);
                        $vendorInfo['materials'][$iterator]['total'] = $vendorInfo['materials'][$iterator]['subtotal'] + $vendorInfo['materials'][$iterator]['cgst_amount'] + $vendorInfo['materials'][$iterator]['sgst_amount'] + $vendorInfo['materials'][$iterator]['igst_amount'];
                        if($purchaseOrderComponent['expected_delivery_date'] == null || $purchaseOrderComponent['expected_delivery_date'] == ''){
                            $vendorInfo['materials'][$iterator]['due_date'] = '';
                        }else{
                            $vendorInfo['materials'][$iterator]['due_date'] = 'Due on '.date('j/n/Y',strtotime($purchaseOrderComponent['expected_delivery_date']));
                        }
                        if($purchaseOrderRequestComponent['transportation_amount'] == null || $purchaseOrderRequestComponent['transportation_amount'] == ''){
                            $vendorInfo['materials'][$iterator]['transportation_amount'] = 0;
                        }else{
                            $vendorInfo['materials'][$iterator]['transportation_amount'] = $purchaseOrderRequestComponent['transportation_amount'];
                        }
                        if($purchaseOrderRequestComponent['transportation_cgst_percentage'] == null || $purchaseOrderRequestComponent['transportation_cgst_percentage'] == ''){
                            $vendorInfo['materials'][$iterator]['transportation_cgst_percentage'] = 0;
                        }else{
                            $vendorInfo['materials'][$iterator]['transportation_cgst_percentage'] = $purchaseOrderRequestComponent['transportation_cgst_percentage'];
                        }
                        if($purchaseOrderRequestComponent['transportation_sgst_percentage'] == null || $purchaseOrderRequestComponent['transportation_sgst_percentage'] == ''){
                            $vendorInfo['materials'][$iterator]['transportation_sgst_percentage'] = 0;
                        }else{
                            $vendorInfo['materials'][$iterator]['transportation_sgst_percentage'] = $purchaseOrderRequestComponent['transportation_sgst_percentage'];
                        }
                        if($purchaseOrderRequestComponent['transportation_igst_percentage'] == null || $purchaseOrderRequestComponent['transportation_igst_percentage'] == ''){
                            $vendorInfo['materials'][$iterator]['transportation_igst_percentage'] = 0;
                        }else{
                            $vendorInfo['materials'][$iterator]['transportation_igst_percentage'] = $purchaseOrderRequestComponent['transportation_igst_percentage'];
                        }
                        $vendorInfo['materials'][$iterator]['transportation_cgst_amount'] = ($vendorInfo['materials'][$iterator]['transportation_cgst_percentage'] * $vendorInfo['materials'][$iterator]['transportation_amount']) / 100 ;
                        $vendorInfo['materials'][$iterator]['transportation_sgst_amount'] = ($vendorInfo['materials'][$iterator]['transportation_sgst_percentage'] * $vendorInfo['materials'][$iterator]['transportation_amount']) / 100 ;
                        $vendorInfo['materials'][$iterator]['transportation_igst_amount'] = ($vendorInfo['materials'][$iterator]['transportation_igst_percentage'] * $vendorInfo['materials'][$iterator]['transportation_amount']) / 100 ;
                        $vendorInfo['materials'][$iterator]['transportation_total_amount'] = $vendorInfo['materials'][$iterator]['transportation_amount'] + $vendorInfo['materials'][$iterator]['transportation_cgst_amount'] + $vendorInfo['materials'][$iterator]['transportation_sgst_amount'] + $vendorInfo['materials'][$iterator]['transportation_igst_amount'];
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
                        $iterator++;
                    }
                    /*Send Mail*/
                    $projectSiteInfo = array();
                    $projectSiteInfo['project_name'] = $purchaseOrderRequest->purchaseRequest->projectSite->project->name;
                    $projectSiteInfo['project_site_name'] = $purchaseOrderRequest->purchaseRequest->projectSite->name;
                    $projectSiteInfo['project_site_address'] = $purchaseOrderRequest->purchaseRequest->projectSite->address;
                    if($purchaseOrderRequest->purchaseRequest->projectSite->city_id == null){
                        $projectSiteInfo['project_site_city'] = '';
                    }else{
                        $projectSiteInfo['project_site_city'] = $purchaseOrderRequest->purchaseRequest->projectSite->city->name;
                    }
                    $pdf = App::make('dompdf.wrapper');
                    $pdfFlag = "purchase-order-listing-download";
                    $pdf->loadHTML(view('purchase.purchase-request.pdf.vendor-quotation')->with(compact('vendorInfo','projectSiteInfo','pdfFlag')));
                    $pdfDirectoryPath = env('PURCHASE_VENDOR_ASSIGNMENT_PDF_FOLDER');
                    $pdfFileName = sha1($vendorId).'.pdf';
                    $pdfUploadPath = env('WEB_PUBLIC_PATH').$pdfDirectoryPath.'/'.$pdfFileName;
                    $pdfContent = $pdf->stream();
                    if(file_exists($pdfUploadPath)){
                        unlink($pdfUploadPath);
                    }
                    if (!file_exists($pdfDirectoryPath)) {
                        File::makeDirectory(env('WEB_PUBLIC_PATH').$pdfDirectoryPath, $mode = 0777, true, true);
                    }
                    file_put_contents($pdfUploadPath,$pdfContent);
                    $mailData = ['path' => $pdfUploadPath, 'toMail' => $vendorInfo['email']];
                    Mail::send('purchase.purchase-request.email.vendor-quotation', [], function($message) use ($mailData){
                        $message->subject('Testing with attachment');
                        $message->to($mailData['toMail']);
                        $message->from(env('MAIL_USERNAME'));
                        $message->attach($mailData['path']);
                    });
                    if($purchaseOrder->is_client_order == true){
                        $mailInfoData = [
                            'user_id' => Auth::user()->id,
                            'type_slug' => 'for-purchase-order',
                            'client_id' => $purchaseOrder->client_id,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                        ];
                    }else{
                        $mailInfoData = [
                            'user_id' => Auth::user()->id,
                            'type_slug' => 'for-purchase-order',
                            'vendor_id' => $purchaseOrder->vendor_id,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                        ];
                    }
                    PurchaseRequestComponentVendorMailInfo::insert($mailInfoData);
                    unlink($pdfUploadPath);
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

}
