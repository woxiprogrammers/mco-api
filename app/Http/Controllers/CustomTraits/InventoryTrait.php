<?php

namespace App\Http\Controllers\CustomTraits;

use App\GRNCount;
use App\InventoryComponent;
use App\InventoryComponentTransferImage;
use App\InventoryComponentTransfers;
use App\InventoryComponentTransferStatus;
use App\InventoryTransferTypes;
use App\Material;
use App\ProjectSite;
use App\Unit;
use App\UnitConversion;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

trait InventoryTrait{
    /*public function createInventoryTransfer(Request $request){
        try{
            $requestData = $request->except('name','type','token','images','project_site_id');

            $inventoryComponentTransferDataId = $this->create($requestData,$request['name'],$request['type'],'from-api',$request['images']);
            if($request['name'] == 'site'){
                $componentData = InventoryComponent::where('id',$request['inventory_component_id'])->first();
                $alreadyPresent = InventoryComponent::where('name','ilike',$componentData['name'])->where('project_site_id',$request['project_site_id'])->first();
                if($alreadyPresent != null){
                    $inventoryComponentId = $alreadyPresent['id'];
                }else{
                    $inventoryData['is_material'] = $componentData['is_material'];
                    $inventoryData['reference_id']  = $componentData['reference_id'];
                    $inventoryData['name'] = $componentData['name'];
                    $inventoryData['project_site_id'] = $request['project_site_id'];
                    $inventoryData['opening_stock'] = 0;
                    $inventoryComponent = InventoryComponent::create($inventoryData);
                    $inventoryComponentId = $inventoryComponent->id;
                }
                $requestData['inventory_component_id'] = $inventoryComponentId;
                $requestData['source_name'] = ProjectSite::where('id',$request['project_site_id'])->pluck('name')->first();
                $inventoryComponentTransferINDataId = $this->create($requestData,'office','IN','from-api',null);
                $inventoryComponentTransferImages = InventoryComponentTransferImage::where('inventory_component_transfer_id',$inventoryComponentTransferDataId)->get();
                if(count($inventoryComponentTransferImages) > 0){
                    $sha1InventoryComponentId = sha1($inventoryComponentId);
                    $sha1InventoryTransferId = sha1($inventoryComponentTransferINDataId);
                    foreach ($inventoryComponentTransferImages as $key1 => $image){
                        $tempUploadFile = env('WEB_PUBLIC_PATH').env('INVENTORY_TRANSFER_IMAGE_UPLOAD').sha1($request['inventory_component_id']).DIRECTORY_SEPARATOR.'transfers'.DIRECTORY_SEPARATOR.sha1($inventoryComponentTransferDataId);
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('INVENTORY_TRANSFER_IMAGE_UPLOAD').$sha1InventoryComponentId.DIRECTORY_SEPARATOR.'transfers'.DIRECTORY_SEPARATOR.$sha1InventoryTransferId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$image['name'];
                        File::copy($tempUploadFile,$imageUploadNewPath);
                        InventoryComponentTransferImage::create(['name' => $image['name'],'inventory_component_transfer_id' => $inventoryComponentTransferINDataId]);
                    }
                }
            }
            $message = "Inventory Component moved successfully";
            $status = 200;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Create Transfer for inventory',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
        ];
            return response()->json($response,$status);
    }*/

    public function createInventoryTransfer(Request $request){
    try{
        switch ($request['name']){
            case 'site' :
                    if($request->has('inventory_component_id')){
                        $inventoryComponentId = $request['inventory_component_id'];
                    }else{
                        $inventoryData = $request->only('is_material','reference_id');
                        $inventoryData['project_site_id'] = $request['project_site_id_to'];
                        $inventoryData['name'] = $request['component_name'];
                        $inventoryData['opening_stock'] = 0;
                        $inventoryComponent = InventoryComponent::create($inventoryData);
                        $inventoryComponentId = $inventoryComponent->id;
                    }
                    $requestData = $request->only('quantity','unit_id','remark');
                    $requestData['inventory_component_id'] = $inventoryComponentId;
                    $projectSite = ProjectSite::where('id',$request['project_site_id_from'])->first();
                    $requestData['source_name'] = $projectSite->project->name.'-'.$projectSite->name;
                    $requestData['date'] = Carbon::now();

                    $baseInventoryComponentTransfer = InventoryComponentTransfers::where('grn',$request['grn'])->first();
                    if($request['is_material'] == true){
                        $requestData['rate_per_unit'] = $baseInventoryComponentTransfer['rate_per_unit'];
                        $requestData['cgst_percentage'] = $baseInventoryComponentTransfer['cgst_percentage'];
                        $requestData['sgst_percentage'] = $baseInventoryComponentTransfer['sgst_percentage'];
                        $requestData['igst_percentage'] = $baseInventoryComponentTransfer['igst_percentage'];
                        $subtotal = $requestData['quantity'] * $requestData['rate_per_unit'];
                        $requestData['cgst_amount'] = $subtotal * ($requestData['cgst_percentage'] / 100) ;
                        $requestData['sgst_amount'] = $subtotal * ($requestData['sgst_percentage'] / 100) ;
                        $requestData['igst_amount'] = $subtotal * ($requestData['igst_percentage'] / 100) ;
                        $requestData['total'] = $subtotal + $requestData['cgst_amount'] + $requestData['sgst_amount'] + $requestData['igst_amount'];
                    }else{
                        $data['rate_per_unit'] = $baseInventoryComponentTransfer['rate_per_unit'];
                    }
                    $inventoryComponentTransferDataId = $this->create($requestData,$request['name'],$request['type'],'from-api',$request['images']);
                break;

            case 'user' :
                $requestData = $request->only('inventory_component_id','quantity','unit_id','remark','source_name');
                $requestData['date'] = Carbon::now();
                $inventoryComponentTransferDataId = $this->create($requestData,$request['name'],$request['type'],'from-api',$request['images']);
                break;
        }
        $message = "Inventory Component moved successfully";
        $status = 200;
    }catch(\Exception $e){
        $message = "Fail";
        $status = 500;
        $data = [
            'action' => 'Create Transfer for inventory',
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

    public function create($request,$name,$type,$slug,$images=null){
        try{
            $inventoryComponentTransferData = $request;
            $selectedTransferType = InventoryTransferTypes::where('slug',$name)->where('type','ilike',$type)->first();
            $inventoryComponentTransferData['transfer_type_id'] = $selectedTransferType->id;
            $inventoryComponentTransferData['inventory_component_transfer_status_id'] = InventoryComponentTransferStatus::where('slug','approved')->pluck('id')->first();
            if($slug == 'from-api'){
                $currentDate = Carbon::now();
                $monthlyGrnGeneratedCount = GRNCount::where('month',$currentDate->month)->where('year',$currentDate->year)->pluck('count')->first();
                if($monthlyGrnGeneratedCount != null){
                    $serialNumber = $monthlyGrnGeneratedCount + 1;
                }else{
                    $serialNumber = 1;
                }
                $inventoryComponentTransferData['grn'] = "GRN".date('Ym').($serialNumber);
            }
            $inventoryComponentTransferData['in_time'] = $inventoryComponentTransferData['out_time'] = Carbon::now();
            $inventoryComponentTransfer = InventoryComponentTransfers::create($inventoryComponentTransferData);
            $inventoryComponentTransferDataId = $inventoryComponentTransfer->id;
            if($name == 'site' && ($type == 'out' || $type == 'OUT')){
                $materialSiteTransferApproveTokens = User::join('user_has_permissions','user_has_permissions.user_id','=','users.id')
                                                        ->join('permissions','permissions.id','=','user_has_permissions.permission_id')
                                                        ->join('user_project_site_relation','user_project_site_relation.user_id','=','users.id')
                                                        ->where('permissions.name','ilike','approve-component-transfer')
                                                        ->where('user_project_site_relation.project_site_id',$inventoryComponentTransfer->inventoryComponent->project_site_id)
                                                        ->select('users.web_fcm_token as web_fcm_token','users.mobile_fcm_token')
                                                        ->get()->toArray();
                $webTokens = array_column($materialSiteTransferApproveTokens,'web_fcm_token');
                $mobileTokens = array_column($materialSiteTransferApproveTokens,'mobile_fcm_token');
                $notificationString = $inventoryComponentTransfer->inventoryComponent->projectSite->project->name.'-'.$inventoryComponentTransfer->inventoryComponent->projectSite->name.' ';
                $notificationString .= 'Stock transferred to '.$inventoryComponentTransfer->source_name.' ';
                $notificationString .= $inventoryComponentTransfer->inventoryComponent->name.' - '.$inventoryComponentTransfer->quantity.' and '.$inventoryComponentTransfer->unit->name;
                $this->sendPushNotification('Manish Construction',$notificationString,$webTokens,$mobileTokens,'c-m-s-t');
            }elseif($name == 'user' && ($type == 'out' || $type == 'OUT')){
                $purchaseRequestApproveAclTokens = User::join('user_has_permissions','user_has_permissions.user_id','=','users.id')
                                                        ->join('permissions','permissions.id','=','user_has_permissions.permission_id')
                                                        ->join('user_project_site_relation','user_project_site_relation.user_id','=','users.id')
                                                        ->where('permissions.name','ilike','approve-purchase-request')
                                                        ->where('user_project_site_relation.project_site_id',$inventoryComponentTransfer->inventoryComponent->project_site_id)
                                                        ->select('users.web_fcm_token as web_fcm_token','users.mobile_fcm_token')
                                                        ->get()->toArray();
                $webTokens = array_column($purchaseRequestApproveAclTokens,'web_fcm_token');
                $mobileTokens = array_column($purchaseRequestApproveAclTokens,'mobile_fcm_token');
                $notificationString = $inventoryComponentTransfer->inventoryComponent->projectSite->project->name.' - '.$inventoryComponentTransfer->inventoryComponent->projectSite->name.' ';
                $notificationString .= 'Material consumed by user '.$inventoryComponentTransfer->user->first_name.' '.$inventoryComponentTransfer->user->last_name.' ';
                $notificationString .= $inventoryComponentTransfer->inventoryComponent->name.' - '.$inventoryComponentTransfer->quantity.' and '.$inventoryComponentTransfer->unit->name;
                $this->sendPushNotification('Manisha Construction', $notificationString,$webTokens,$mobileTokens,'c-m-u-o-t');
            }elseif($name == 'site' && ($type == 'in' || $type == 'IN')){
                $fromProjectSitesArray = explode('-', $inventoryComponentTransfer->source_name);
                $projectSiteId = ProjectSite::join('projects','projects.id','=','project_sites.project_id')
                                    ->where('project_sites.name','ilike', trim($fromProjectSitesArray[1]))
                                    ->where('projects.name','ilike', trim($fromProjectSitesArray[0]))
                                    ->pluck('project_sites.id')->first();
                $fromInventoryComponentId = InventoryComponent::where('project_site_id', $projectSiteId)
                                            ->where('name','ilike', $inventoryComponentTransfer->inventoryComponent->name)
                                            ->pluck('id')->first();
                $siteOutTransferTypeId = InventoryTransferTypes::where('slug','site')->where('type','ilike','out')->plcuk('id')->first();
                $lastOutInventoryComponentTransfer = InventoryComponentTransfers::where('inventory_component_id', $fromInventoryComponentId)
                                                                    ->where('transfer_type_id', $siteOutTransferTypeId)
                                                                    ->where('source_name','ilike',$inventoryComponentTransfer->inventoryComponent->projectSite->project->name.'-'.$inventoryComponentTransfer->inventoryComponent->projectSite->name)
                                                                    ->orderBy('created_at', 'desc')
                                                                    ->first();
                $webTokens = [$lastOutInventoryComponentTransfer->user->web_fcm_token];
                $mobileTokens = [$lastOutInventoryComponentTransfer->user->mobile_fcm_token];
                $notificationString = 'From '.$inventoryComponentTransfer->source_name.' stock received to ';
                $notificationString .= $inventoryComponentTransfer->inventoryComponent->projectSite->project->name.' - '.$inventoryComponentTransfer->inventoryComponent->projectSite->name.' ';
                $notificationString .= $inventoryComponentTransfer->inventoryComponent->name.' - '.$inventoryComponentTransfer->quantity.' and '.$inventoryComponentTransfer->unit->name;
                $this->sendPushNotification('Manisha Construction', $notificationString,$webTokens,$mobileTokens,'c-m-s-i-t');
            }
            if($slug == 'from-api') {
                if ($monthlyGrnGeneratedCount != null) {
                    GRNCount::where('month', $currentDate->month)->where('year', $currentDate->year)->update(['count' => $serialNumber]);
                } else {
                    GRNCount::create(['month' => $currentDate->month, 'year' => $currentDate->year, 'count' => $serialNumber]);
                }
                if ($images != null) {
                    $user = Auth::user();
                    $sha1UserId = sha1($user['id']);
                    $sha1InventoryTransferId = sha1($inventoryComponentTransferDataId);
                    $sha1InventoryComponentId = sha1($request['inventory_component_id']);
                    foreach ($request['images'] as $key1 => $imageName) {
                        $tempUploadFile = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_TEMP_IMAGE_UPLOAD') . $sha1UserId . DIRECTORY_SEPARATOR . $imageName;
                        if (File::exists($tempUploadFile)) {
                            $imageUploadNewPath = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_IMAGE_UPLOAD') . $sha1InventoryComponentId . DIRECTORY_SEPARATOR . 'transfers' . DIRECTORY_SEPARATOR . $sha1InventoryTransferId;
                            if (!file_exists($imageUploadNewPath)) {
                                File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                            }
                            $imageUploadNewPath .= DIRECTORY_SEPARATOR . $imageName;
                            File::move($tempUploadFile, $imageUploadNewPath);
                            InventoryComponentTransferImage::create(['name' => $imageName, 'inventory_component_transfer_id' => $inventoryComponentTransferDataId]);
                        }
                    }
                }
            }
        }catch (\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Create',
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        return $inventoryComponentTransferDataId;
    }

    public function getSiteTransferRequestListing(Request $request){
        try{
            $inventoryComponentIds = InventoryComponent::where('project_site_id',$request['project_site_id'])->pluck('id');
            $inventoryTransferData = InventoryComponentTransfers::where('inventory_component_transfer_status_id',InventoryComponentTransferStatus::where('slug','requested')->pluck('id')->first())
                                        ->whereIn('inventory_component_id',$inventoryComponentIds)->orderBy('created_at','desc')->get();
            $request_component_listing = array();
            $iterator = 0;
            foreach ($inventoryTransferData as $key => $inventoryTransfer) {
                $request_component_listing[$iterator]['inventory_component_transfer_id'] = $inventoryTransfer['id'];
                $request_component_listing[$iterator]['project_site_from'] = $inventoryTransfer->inventoryComponent->projectSite->name;
                $request_component_listing[$iterator]['project_site_to'] = $inventoryTransfer->source_name;
                $request_component_listing[$iterator]['component_name'] = $inventoryTransfer->inventoryComponent->name;
                $request_component_listing[$iterator]['quantity'] = $inventoryTransfer->quantity;
                $request_component_listing[$iterator]['unit'] = $inventoryTransfer->unit->name;
                $iterator++;
            }
            $data['request_component_listing'] = $request_component_listing;
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Request Component listing',
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
            InventoryComponentTransfers::where('id',$request['inventory_component_transfer_id'])
                ->update([
                    'inventory_component_transfer_status_id' =>  InventoryComponentTransferStatus::where('slug',$request['change_status_slug_to'])->pluck('id')->first()
                ]);
            $message = "Inventory Component Transfer Status Changed Successfully";
            $status = 200;
        }catch (\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Change Status',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message,
        ];
        return response()->json($response,$status);
    }

    public function autoSuggest(Request $request){
        try{
            $message = 'Success';
            $status = 200;
            $data = array();
            if($request['search_in'] == 'material'){
                $fromProjectSiteComponents = InventoryComponent::where('name','ilike','%'.$request['keyword'].'%')
                                                ->where('is_material',true)
                                                ->where('project_site_id',$request['project_site_id_to'])
                                                ->select('name','reference_id','id as inventory_component_id')->get();
                $alreadyExitMaterialsIds = $fromProjectSiteComponents->pluck('reference_id');
                $toProjectSiteComponents = InventoryComponent::where('name','ilike','%'.$request['keyword'].'%')->where('is_material',true)->whereNotIn('reference_id',$alreadyExitMaterialsIds)->where('project_site_id',$request['project_site_id_from'])->distinct('name')->select('name','reference_id')->get();
                $iterator = 0;
                $components = array_merge($fromProjectSiteComponents->toArray(),$toProjectSiteComponents->toArray());
                foreach($components as $key => $component){
                    $data[$iterator]['name'] = $component['name'];
                    $data[$iterator]['reference_id'] = $component['reference_id'];
                    $data[$iterator]['inventory_component_id'] = (array_key_exists('inventory_component_id',$component)) ? $component['inventory_component_id'] : null;
                    $data[$iterator]['unit'] = array();
                    $materialUnitId = Material::where('id',$component['reference_id'])->pluck('unit_id')->first();
                    $data[$iterator]['unit'][0]['unit_id'] = $materialUnitId;
                    $data[$iterator]['unit'][0]['unit_name'] =  Unit::where('id',$materialUnitId)->pluck('name')->first();
                    $unitConversionIds1 = UnitConversion::where('unit_1_id',$materialUnitId)->pluck('unit_2_id');
                    $unitConversionIds2 = UnitConversion::where('unit_2_id',$materialUnitId)->pluck('unit_1_id');
                    $unitConversionNeededIds = array_merge($unitConversionIds1->toArray(),$unitConversionIds2->toArray());
                    $jIterator = 1;
                    foreach ($unitConversionNeededIds as $key1 => $unitId){
                        $unit = Unit::where('id',$unitId)->first();
                        $data[$iterator]['unit'][$jIterator]['unit_id'] = $unit->id;
                        $data[$iterator]['unit'][$jIterator]['unit_name'] = $unit->name;
                        $jIterator++;
                    }
                    $iterator++;
                }
            }else{
                $fromProjectSiteComponents = InventoryComponent::where('name','ilike','%'.$request['keyword'].'%')->where('is_material',false)->where('project_site_id',$request['project_site_id_to'])->select('name','reference_id','id as inventory_component_id')->get();
                $alreadyExitMaterialsIds = $fromProjectSiteComponents->pluck('reference_id');
                $toProjectSiteComponents = InventoryComponent::where('name','ilike','%'.$request['keyword'].'%')->where('is_material',false)->whereNotIn('reference_id',$alreadyExitMaterialsIds)->where('project_site_id',$request['project_site_id_from'])->distinct('name')->select('name','reference_id')->get();
                $components = array_merge($fromProjectSiteComponents->toArray(),$toProjectSiteComponents->toArray());
                $iterator = 0;
                $assetUnit = Unit::where('slug','nos')->first();
                foreach($components as $key => $component){
                    $data[$iterator]['name'] = $component['name'];
                    $data[$iterator]['reference_id'] = $component['reference_id'];
                    $data[$iterator]['inventory_component_id'] = (array_key_exists('inventory_component_id',$component)) ? $component['inventory_component_id'] : null;
                    $data[$iterator]['unit'] = array();
                    $data[$iterator]['unit'][0]['unit_id'] = $assetUnit->id;
                    $data[$iterator]['unit'][0]['unit_name'] =  $assetUnit->name;
                    $iterator++;
                }
            }
        }catch(\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Get Inventory Component Auto Suggestion',
                'exception' => $e->getMessage(),
                'data' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response($response,$status);
    }

    public function getGRNDetails(Request $request){
        try{
            $status = 200;
            $message = "Success";
            $inventoryComponentTransfer = InventoryComponentTransfers::where('grn',$request['grn'])->with('inventoryComponent')->first();
            $data['related_inventory_component_transfer_id'] = $inventoryComponentTransfer['id'];
            $data['material_name'] = $inventoryComponentTransfer['inventoryComponent']['name'];
            $data['quantity'] = $inventoryComponentTransfer['quantity'];
            $data['unit_id'] = $inventoryComponentTransfer['unit_id'];
            $data['unit_name'] = $inventoryComponentTransfer->unit->name;
            $data['reference_id'] = $inventoryComponentTransfer['inventoryComponent']['reference_id'];
            $data['rate_per_unit'] = $inventoryComponentTransfer['rate_per_unit'];
            $data['cgst_percentage'] = $inventoryComponentTransfer['cgst_percentage'];
            $data['sgst_percentage'] = $inventoryComponentTransfer['sgst_percentage'];
            $data['igst_percentage'] = $inventoryComponentTransfer['igst_percentage'];
            $data['cgst_amount'] = $inventoryComponentTransfer['cgst_amount'];
            $data['sgst_amount'] = $inventoryComponentTransfer['sgst_amount'];
            $data['igst_amount'] = $inventoryComponentTransfer['igst_amount'];
            $data['total'] = $inventoryComponentTransfer['total'];
            $data['vendor_id'] = $inventoryComponentTransfer['vendor_id'];
            $data['vendor_name'] = $inventoryComponentTransfer->vendor->name;
            $data['transportation_amount'] = $inventoryComponentTransfer['transportation_amount'] ;
            $transportation_cgst_amount = ($inventoryComponentTransfer['transportation_amount'] * $inventoryComponentTransfer['transportation_cgst_percent']) / 100;
            $transportation_sgst_amount = ($inventoryComponentTransfer['transportation_amount'] * $inventoryComponentTransfer['transportation_sgst_percent']) / 100;
            $transportation_igst_amount = ($inventoryComponentTransfer['transportation_amount'] * $inventoryComponentTransfer['transportation_igst_percent']) / 100;
            $data['transportation_tax_amount'] = $transportation_cgst_amount + $transportation_sgst_amount + $transportation_igst_amount;
            $data['driver_name'] = $inventoryComponentTransfer['driver_name'];
            $data['vehicle_number'] = $inventoryComponentTransfer['vehicle_number'];
            $data['mobile'] = $inventoryComponentTransfer['mobile'];
            $data['project_site_id'] = $inventoryComponentTransfer['inventoryComponent']['project_site_id'];
            $data['project_details'] = $inventoryComponentTransfer['source_name'];
        }catch(\Exception $e){
            $data = [
                'action' => 'Get GRN Details',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            $status = 500;
            $response = array();
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function generateGRN(Request $request){
        try {
            $user = Auth::user();
            $relatedInventoryComponentTransferData = InventoryComponentTransfers::where('id', $request['related_inventory_component_transfer_id'])->first();
            $inventoryComponentId = InventoryComponent::where('project_site_id', $request['project_site_id'])->where('name', 'ilike', $relatedInventoryComponentTransferData->inventoryComponent->name)->pluck('id')->first();
            if (count($inventoryComponentId) == 0) {
                $relatedInventoryComponentData = $relatedInventoryComponentTransferData->inventoryComponent;
                $inventoryData['project_site_id'] = $request['project_site_id'];
                $inventoryData['name'] = $relatedInventoryComponentData['name'];
                $inventoryData['is_material'] = $relatedInventoryComponentData['is_material'];
                $inventoryData['reference_id'] = $relatedInventoryComponentData['reference_id'];
                $inventoryData['opening_stock'] = 0;
                $inventoryComponent = InventoryComponent::create($inventoryData);
                $inventoryComponentId = $inventoryComponent->id;
            }
            $projectSite = ProjectSite::where('id',$relatedInventoryComponentTransferData->inventoryComponent->project_site_id)->first();
            $sourceName = $projectSite->project->name.'-'.$projectSite->name;
            $currentDate = Carbon::now();
            $monthlyGrnGeneratedCount = GRNCount::where('month',$currentDate->month)->where('year',$currentDate->year)->pluck('count')->first();
            if($monthlyGrnGeneratedCount != null){
                $serialNumber = $monthlyGrnGeneratedCount + 1;
            }else{
                $serialNumber = 1;
            }
            $grn = "GRN".date('Ym').($serialNumber);
            $inventoryComponentTransfer = InventoryComponentTransfers::create([
                                                'inventory_component_id' => $inventoryComponentId,
                                                'transfer_type_id' => InventoryTransferTypes::where('slug','site')->where('type','ilike','IN')->pluck('id')->first(),
                                                'quantity' => $request['quantity'],
                                                'unit_id' => $relatedInventoryComponentTransferData['unit_id'],
                                                'source_name' => $sourceName,
                                                'bill_number' => $relatedInventoryComponentTransferData['bill_number'],
                                                'bill_amount' => $relatedInventoryComponentTransferData['bill_amount'],
                                                'vehicle_number' => $relatedInventoryComponentTransferData['vehicle_number'],
                                                'in_time' => $currentDate,
                                                'user_id' => $user['id'],
                                                'grn' => $grn,
                                                'inventory_component_transfer_status_id' => InventoryComponentTransferStatus::where('slug','grn-generated')->pluck('id')->first(),
                                                'rate_per_unit' => $relatedInventoryComponentTransferData['rate_per_unit'],
                                                'cgst_percentage' => $relatedInventoryComponentTransferData['cgst_percentage'],
                                                'sgst_percentage' => $relatedInventoryComponentTransferData['sgst_percentage'],
                                                'igst_percentage' => $relatedInventoryComponentTransferData['igst_percentage'],
                                                'cgst_amount' => $relatedInventoryComponentTransferData['cgst_amount'],
                                                'sgst_amount' => $relatedInventoryComponentTransferData['sgst_amount'],
                                                'igst_amount' => $relatedInventoryComponentTransferData['igst_amount'],
                                                'total' => $relatedInventoryComponentTransferData['total'],
                                                'vendor_id' => $relatedInventoryComponentTransferData['vendor_id'],
                                                'transportation_amount' => $relatedInventoryComponentTransferData['transportation_amount'],
                                                'transportation_cgst_percent' => $relatedInventoryComponentTransferData['transportation_cgst_percent'],
                                                'transportation_sgst_percent' => $relatedInventoryComponentTransferData['transportation_sgst_percent'],
                                                'transportation_igst_percent' => $relatedInventoryComponentTransferData['transportation_igst_percent'],
                                                'driver_name' => $relatedInventoryComponentTransferData['driver_name'],
                                                'mobile' => $relatedInventoryComponentTransferData['mobile'],
                                                'related_transfer_id' => $relatedInventoryComponentTransferData['id']
                                            ]);

            $relatedInventoryComponentTransferData->update(['related_transfer_id' => $inventoryComponentTransfer['id']]);
            if ($monthlyGrnGeneratedCount != null) {
                GRNCount::where('month', $currentDate->month)->where('year', $currentDate->year)->update(['count' => $serialNumber]);
            } else {
                GRNCount::create(['month' => $currentDate->month, 'year' => $currentDate->year, 'count' => $serialNumber]);
            }
            if ($request->has('images')) {
                $sha1UserId = sha1($user['id']);
                $sha1InventoryTransferId = sha1($inventoryComponentTransfer['id']);
                $sha1InventoryComponentId = sha1($request['inventory_component_id']);
                foreach ($request['images'] as $key1 => $imageName) {
                    $tempUploadFile = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_TEMP_IMAGE_UPLOAD') . $sha1UserId . DIRECTORY_SEPARATOR . $imageName;
                    if (File::exists($tempUploadFile)) {
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_IMAGE_UPLOAD') . $sha1InventoryComponentId . DIRECTORY_SEPARATOR . 'transfers' . DIRECTORY_SEPARATOR . $sha1InventoryTransferId;
                        if (!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR . $imageName;
                        File::move($tempUploadFile, $imageUploadNewPath);
                        InventoryComponentTransferImage::create(['name' => $imageName, 'inventory_component_transfer_id' => $inventoryComponentTransfer['id']]);
                    }
                }
            }
            $status = 200;
            $message = "Success";
            $data['grn'] = $grn;
            $data['inventory_component_id'] = $inventoryComponentTransfer['id'];
        }catch(\Exception $e){
            $message = "Something went wrong";
            $data = [
                'action' => 'Generate GRN',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            $status = 500;
            $response = array();
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
        }

    public function getSiteOutGRN(Request $request){
        try{
            $status = 200;
            $message = "Success";
            $grns = InventoryComponentTransfers::join('inventory_components','inventory_components.id','=','inventory_component_transfers.inventory_component_id')
                        ->where('transfer_type_id',InventoryTransferTypes::where('slug','site')->where('type','ilike','out')->pluck('id')->first())
                        ->where('inventory_component_transfers.inventory_component_transfer_status_id',InventoryComponentTransferStatus::where('slug','approved')->pluck('id')->first())
                        ->where('inventory_components.project_site_id','!=',$request['project_site_id'])
                        ->whereNull('related_transfer_id')->select('inventory_component_transfers.id','inventory_component_transfers.grn')->get();
            $data['grn'] = $grns;
        }catch(\Exception $e){
            $message = "Something went wrong";
            $data = [
                'action' => 'Get site out GRNs',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            $status = 500;
            $response = array();
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function postGrnInventoryTransfer(Request $request){
        try{
            $user = Auth::user();
            $status = 200;
            $message = "Success";
            $inventoryComponentTransfer = InventoryComponentTransfers::where('id',$request['inventory_component_transfer_id'])->first();
            $now = Carbon::now();
            $inventoryComponentTransfer->update([
                'out_time' => $now,
                'date' => $now,
                'inventory_component_transfer_status_id' => InventoryComponentTransferStatus::where('slug','approved')->pluck('id')->first(),
                'remark' => $request['remark']
            ]);
            if ($request->has('images')) {
                $sha1UserId = sha1($user['id']);
                $sha1InventoryTransferId = sha1($inventoryComponentTransfer['id']);
                $sha1InventoryComponentId = sha1($inventoryComponentTransfer['inventory_component_id']);
                foreach ($request['images'] as $key1 => $imageName) {
                    $tempUploadFile = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_TEMP_IMAGE_UPLOAD') . $sha1UserId . DIRECTORY_SEPARATOR . $imageName;
                    if (File::exists($tempUploadFile)) {
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_IMAGE_UPLOAD') . $sha1InventoryComponentId . DIRECTORY_SEPARATOR . 'transfers' . DIRECTORY_SEPARATOR . $sha1InventoryTransferId;
                        if (!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR . $imageName;
                        File::move($tempUploadFile, $imageUploadNewPath);
                        InventoryComponentTransferImage::create(['name' => $imageName, 'inventory_component_transfer_id' => $inventoryComponentTransfer['id']]);
                    }
                }
            }
            $fromProjectSitesArray = explode('-', $inventoryComponentTransfer->source_name);
            $projectSiteId = ProjectSite::join('projects','projects.id','=','project_sites.project_id')
                ->where('project_sites.name','ilike', trim($fromProjectSitesArray[1]))
                ->where('projects.name','ilike', trim($fromProjectSitesArray[0]))
                ->pluck('project_sites.id')->first();
            $fromInventoryComponentId = InventoryComponent::where('project_site_id', $projectSiteId)
                ->where('name','ilike', $inventoryComponentTransfer->inventoryComponent->name)
                ->pluck('id')->first();
            $siteOutTransferTypeId = InventoryTransferTypes::where('slug','site')->where('type','ilike','out')->plcuk('id')->first();
            $lastOutInventoryComponentTransfer = InventoryComponentTransfers::where('inventory_component_id', $fromInventoryComponentId)
                ->where('transfer_type_id', $siteOutTransferTypeId)
                ->where('source_name','ilike',$inventoryComponentTransfer->inventoryComponent->projectSite->project->name.'-'.$inventoryComponentTransfer->inventoryComponent->projectSite->name)
                ->orderBy('created_at', 'desc')
                ->first();
            $webTokens = [$lastOutInventoryComponentTransfer->user->web_fcm_token];
            $mobileTokens = [$lastOutInventoryComponentTransfer->user->mobile_fcm_token];
            $notificationString = 'From '.$inventoryComponentTransfer->source_name.' stock received to ';
            $notificationString .= $inventoryComponentTransfer->inventoryComponent->projectSite->project->name.' - '.$inventoryComponentTransfer->inventoryComponent->projectSite->name.' ';
            $notificationString .= $inventoryComponentTransfer->inventoryComponent->name.' - '.$inventoryComponentTransfer->quantity.' and '.$inventoryComponentTransfer->unit->name;
            $this->sendPushNotification('Manisha Construction', $notificationString,$webTokens,$mobileTokens,'c-m-s-i-t');

        }catch(\Exception $e){
            $message = "Something went wrong";
            $data = [
                'action' => 'Post GRN Inventory component transfer',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            $status = 500;
            $response = array();
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response,$status);
    }
}