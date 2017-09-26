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
        $alreadyCreatedMaterialRequest = MaterialRequests::where('project_site_id',$data['project_site_id'])->where('user_id',$user['id'])->first();
        if(count($alreadyCreatedMaterialRequest) > 0){
            $materialRequest = $alreadyCreatedMaterialRequest;
        }else{
            $materialRequest['project_site_id'] = $data['project_site_id'];
            $materialRequest['user_id'] = $user['id'];
            $materialRequest['quotation_id'] = $quotationId != null ? $quotationId['id'] : null;
            $materialRequest['assigned_to'] = $data['assigned_to'];
            $materialRequest = MaterialRequests::create($materialRequest);
            $materialRequest['id'] = 1;
        }
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
                foreach($itemData['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('MATERIAL_REQUEST_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('MATERIAL_REQUEST_IMAGE_UPLOAD').$sha1UserId;
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

    public function saveMaterialRequestImages(Request $request){
        try{
            $user = Auth::user();
            $sha1UserId = sha1($user['id']);
            $tempUploadPath = env('WEB_PUBLIC_PATH').env('MATERIAL_REQUEST_TEMP_IMAGE_UPLOAD');
            $tempImageUploadPath = $tempUploadPath.$sha1UserId;
            if (!file_exists($tempImageUploadPath)) {
                File::makeDirectory($tempImageUploadPath, $mode = 0777, true, true);
            }
            $extension = $request->file('image')->getClientOriginalExtension();
            $filename = mt_rand(1,10000000000).sha1(time()).".{$extension}";
            $request->file('image')->move($tempImageUploadPath,$filename);
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $data = [
                'action' => 'Save Material Request Images',
                'request' => $request->all(),
                'exception' => $e->getMessage()
            ];
            $message = "Fail";
            $status = 500;
            $filename = null;
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message,
            "filename" => $filename
        ];
        return response()->json($response,$status);
    }
}