<?php


namespace App\Http\Controllers\CustomTraits;

use App\MaterialRequestComponentImages;
use App\MaterialRequestComponents;
use App\MaterialRequests;
use App\PurchaseRequestComponentStatuses;
use App\Quotation;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait MaterialRequestTrait{

    public function createMaterialRequest($data,$user,$is_purchase_request){
        $quotationId = Quotation::where('project_site_id',$data['project_site_id'])->pluck('id')->first();
        $materialRequest['project_site_id'] = $data['project_site_id'];
        $materialRequest['user_id'] = $user['id'];
        $materialRequest['quotation_id'] = $quotationId != null ? $quotationId['id'] : null;
        $materialRequest['assigned_to'] = $data['assigned_to'];
        $materialRequest = MaterialRequests::create($materialRequest);
        $iterator = 0;
        $materialRequestComponent = array();
        foreach($data['item_list'] as $key => $itemData){
            $materialRequestComponentData['material_request_id'] = $materialRequest['id'];
            $materialRequestComponentData['name'] = $itemData['name'];
            $materialRequestComponentData['quantity'] = $itemData['quantity'];
            $materialRequestComponentData['unit_id'] = $itemData['unit_id'];
            $materialRequestComponentData['component_type_id'] = $itemData['component_type_id'];
            if($is_purchase_request == true){
                $materialRequestComponentData['component_status_id'] = PurchaseRequestComponentStatuses::where('slug','p-r-assigned')->pluck('id')->first();
            }else{
                $materialRequestComponentData['component_status_id'] = PurchaseRequestComponentStatuses::where('slug','pending')->pluck('id')->first();
            }
            $materialRequestComponentData['created_at'] = Carbon::now();
            $materialRequestComponentData['updated_at'] = Carbon::now();
            $materialRequestComponent[$iterator] = MaterialRequestComponents::insertGetId($materialRequestComponentData);
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
    }
}