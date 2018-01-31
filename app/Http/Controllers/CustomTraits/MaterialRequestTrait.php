<?php


namespace App\Http\Controllers\CustomTraits;

use App\MaterialRequestComponentHistory;
use App\MaterialRequestComponentImages;
use App\MaterialRequestComponents;
use App\MaterialRequestComponentVersion;
use App\MaterialRequests;
use App\PurchaseRequestComponentStatuses;
use App\Quotation;
use App\Unit;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait MaterialRequestTrait{
    public function createMaterialRequest($data,$user,$is_purchase_request){
        try{
            $currentDate = Carbon::now();
            $purchaseRequestComponentStatus = PurchaseRequestComponentStatuses::get();
            $materialRequestSerialNoCount = MaterialRequests::whereDate('created_at',$currentDate)->count();
            $quotationId = Quotation::where('project_site_id',$data['project_site_id'])->pluck('id')->first();
            $materialRequestData['project_site_id'] = $data['project_site_id'];
            $materialRequestData['user_id'] = $materialRequestData['on_behalf_of'] = $user['id'];
            $materialRequestData['quotation_id'] = $quotationId != null ? $quotationId['id'] : null;
            $materialRequestData['serial_no'] = $materialRequestSerialNoCount + 1;
            $materialRequestData['format_id'] =  $this->getPurchaseIDFormat('material-request',$data['project_site_id'],Carbon::now(),$materialRequestData['serial_no']);
            $materialRequest = MaterialRequests::create($materialRequestData);
            $iterator = 0;
            $materialRequestComponent = array();
            $materialComponentHistoryData = array();
            $materialComponentHistoryData['component_status_id'] = $purchaseRequestComponentStatus->where('slug','pending')->first()->id;
            $materialComponentHistoryData['remark'] = $materialRequestComponentVersion['remark'] = '';
            $materialComponentHistoryData['user_id'] = $materialRequestComponentVersion['user_id'] = $user['id'];
            $mobileTokens = User::join('user_has_permissions','users.id','=','user_has_permissions.user_id')
                ->join('permissions','permissions.id','=','user_has_permissions.permission_id')
                ->join('user_project_site_relation','users.id','=','user_project_site_relation.user_id')
                ->where('permissions.name','approve-material-request')
                ->whereNotNull('users.mobile_fcm_token')
                ->where('user_project_site_relation.project_site_id',$data['project_site_id'])
                ->pluck('users.mobile_fcm_token')
                ->toArray();
            $webTokens = User::join('user_has_permissions','users.id','=','user_has_permissions.user_id')
                ->join('permissions','permissions.id','=','user_has_permissions.permission_id')
                ->join('user_project_site_relation','users.id','=','user_project_site_relation.user_id')
                ->where('permissions.name','approve-material-request')
                ->whereNotNull('users.web_fcm_token')
                ->where('user_project_site_relation.project_site_id',$data['project_site_id'])
                ->pluck('users.web_fcm_token')
                ->toArray();
            $tokens = array_merge($mobileTokens,$webTokens);
            foreach($data['item_list'] as $key => $itemData){
                $materialRequestComponentData['material_request_id'] = $materialRequest['id'];
                $materialRequestComponentData['name'] = $itemData['name'];
                $materialRequestComponentData['quantity'] = $materialRequestComponentVersion['quantity'] =  $itemData['quantity'];
                $materialRequestComponentData['unit_id'] = $materialRequestComponentVersion['unit_id'] = $itemData['unit_id'];
                $unitName = Unit::where('id',$materialRequestComponentData['unit_id'])->pluck('name')->first();
                $materialRequestComponentData['component_type_id'] = $itemData['component_type_id'];
                if($is_purchase_request == true){
                    $materialRequestComponentData['component_status_id'] = $materialRequestComponentVersion['component_status_id'] = $purchaseRequestComponentStatus->where('slug','p-r-assigned')->first()->id;
                }else{
                    $materialRequestComponentData['component_status_id'] = $materialRequestComponentVersion['component_status_id'] = $purchaseRequestComponentStatus->where('slug','pending')->first()->id;
                }
                $materialRequestComponentSerialNo = MaterialRequestComponents::whereDate('created_at',$currentDate)->count();
                $materialRequestComponentData['serial_no'] = $materialRequestComponentSerialNo + 1;
                $materialRequestComponentData['created_at'] = Carbon::now();
                $materialRequestComponentData['updated_at'] = Carbon::now();
                $materialRequestComponentData['format_id'] =  $this->getPurchaseIDFormat('material-request-component',$data['project_site_id'],$materialRequestComponentData['created_at'],$materialRequestComponentData['serial_no']);
                $materialRequestComponent[$iterator] = MaterialRequestComponents::insertGetId($materialRequestComponentData);
                $notificationString = '<b>1-'.$materialRequest->projectSite->project->name.' '.$materialRequest->projectSite->name.'<b>';
                $notificationString .= ' '.$user['first_name'].' '.$user['last_name'].'<b> Material Request Created.</b><br>';
                $notificationString .= ' '.$itemData['name'].' '.$materialRequestComponentData['quantity'].' '.$unitName;
                $this->sendPushNotification('',$notificationString,$tokens);
                $materialComponentHistoryData['material_request_component_id'] = $materialRequestComponentVersion['material_request_component_id'] = $materialRequestComponent[$iterator];
                MaterialRequestComponentHistory::create($materialComponentHistoryData);
                MaterialRequestComponentVersion::create($materialRequestComponentVersion);
                if(array_has($itemData,'images')){
                    $user = Auth::user();
                    $sha1UserId = sha1($user['id']);
                    $sha1MaterialRequestId = sha1($materialRequest['id']);
                    foreach($itemData['images'] as $key1 => $imageName){
                        $tempUploadFile = env('WEB_PUBLIC_PATH').env('MATERIAL_REQUEST_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                        if(File::exists($tempUploadFile)){
                            $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('MATERIAL_REQUEST_IMAGE_UPLOAD').$sha1MaterialRequestId;
                            if(!file_exists($imageUploadNewPath)) {
                                File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                            }
                            $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                            File::move($tempUploadFile,$imageUploadNewPath);
                            MaterialRequestComponentImages::create(['name' => $imageName,'material_request_component_id' => $materialRequestComponent[$iterator]]);
                        }
                    }
                }
                $iterator++;
            }
            return $materialRequestComponent;
        }catch(\Exception $e){
            $data = [
                'action' => 'Create Material Request in Trait',
                'data' => $data,
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            return null;
        }

    }
}