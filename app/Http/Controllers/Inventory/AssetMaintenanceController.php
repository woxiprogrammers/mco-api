<?php

namespace App\Http\Controllers\Inventory;
use App\AssetMaintenance;
use App\AssetMaintenanceImage;
use App\AssetMaintenanceStatus;
use App\AssetMaintenanceTransaction;
use App\AssetMaintenanceTransactionImages;
use App\AssetMaintenanceTransactionStatuses;
use App\GRNCount;
use App\Http\Controllers\CustomTraits\InventoryTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class AssetMaintenanceController extends BaseController{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

        use InventoryTrait;

        public function createAssetMaintenanceRequest(Request $request){
        try{
            $status = 200;
            $message = "Asset Maintenance Request created successfully";
            $user = Auth::user();
            if($request->has('remark')){
                $assetMaintenance['remark'] = $request['remark'];
            }
            $assetMaintenance = AssetMaintenance::create([
                'asset_id' => $request['asset_id'],
                'project_site_id' => $request['project_site_id'],
                'asset_maintenance_status_id' => AssetMaintenanceStatus::where('slug','maintenance-requested')->pluck('id')->first(),
                'user_id' => $user['id'],
            ]);

            if($request->has('image')){
                $sha1UserId = sha1($user['id']);
                $assetDirectoryName = sha1($assetMaintenance['id']);
                foreach($request['image'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('REQUEST_MAINTENANCE_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('ASSET_MAINTENANCE_REQUEST_IMAGE_UPLOAD').$assetDirectoryName;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                        File::move($tempUploadFile,$imageUploadNewPath);
                        AssetMaintenanceImage::create(['name' => $imageName,'asset_maintenance_id' => $assetMaintenance['id']]);
                    }
                }
            }

        }catch(\Exception $e){
            $data = [
                'action' => 'Create Asset Maintenance Request',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
            abort(500);
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response,$status);
    }

        public function getAssetRequestMaintenanceListing(Request $request){
            try{
                $assetMaintenanceData = AssetMaintenance::where('project_site_id',$request['project_site_id'])->whereMonth('created_at', $request['month'])->whereYear('created_at', $request['year'])
                    ->orderBy('created_at','desc')->get();
                $iterator = 0;
                $assetMaintenanceList = array();
                foreach($assetMaintenanceData as $key => $assetMaintenance){
                    $assetMaintenanceList[$iterator]['asset_maintenance_id'] = $assetMaintenance['id'];
                    $assetMaintenanceList[$iterator]['user_id'] = $assetMaintenance['user_id'];
                    $assetMaintenanceList[$iterator]['user_name'] = $assetMaintenance->user->first_name.' '.$assetMaintenance->user->last_name;
                    $status = $assetMaintenance->assetMaintenanceStatus;
                    $assetMaintenanceList[$iterator]['status'] = $status->name;
                    if($status->slug == 'vendor-approved'){
                        $approvedVendor = $assetMaintenance->assetMaintenanceVendorRelation->where('is_approved',true)->first();
                        $assetMaintenanceList[$iterator]['approved_vendor_id'] = (string)$approvedVendor['vendor_id'];
                        $assetMaintenanceList[$iterator]['approved_vendor_name'] = $approvedVendor->vendor->name;
                    }else{
                        $assetMaintenanceList[$iterator]['approved_vendor_id'] = '';
                        $assetMaintenanceList[$iterator]['approved_vendor_name'] = '';
                    }
                    $grnGeneratedStatusId = AssetMaintenanceTransactionStatuses::where('slug','grn-generated')->pluck('id')->first();
                    $assetMaintenanceTransaction = $assetMaintenance->assetMaintenanceTransaction->where('asset_maintenance_transaction_status_id',$grnGeneratedStatusId)->first();
                    $assetMaintenanceList[$iterator]['images'] = array();
                    if(count($assetMaintenanceTransaction) > 0){
                        $assetMaintenanceList[$iterator]['grn'] = $assetMaintenanceTransaction['grn'];
                        $images = $assetMaintenanceTransaction->assetMaintenanceTransactionImage;
                        $sha1assetMaintenanceId = sha1($assetMaintenance['id']);
                        $sha1assetMaintenanceTransactionId = sha1($assetMaintenanceTransaction['id']);
                        $imageUploadPath = env('ASSET_MAINTENANCE_REQUEST_IMAGE_UPLOAD') . $sha1assetMaintenanceId . DIRECTORY_SEPARATOR . 'bill_transaction' . DIRECTORY_SEPARATOR . $sha1assetMaintenanceTransactionId;
                        if(count($images) > 0){
                            $jIterator = 0;
                            foreach($images as $key1 => $image){
                                $assetMaintenanceList[$iterator]['images'][$jIterator]['image_path'] = $imageUploadPath.DIRECTORY_SEPARATOR.$image['name'];
                            }
                        }
                    }else{
                        $assetMaintenanceList[$iterator]['grn'] = '';
                        $assetMaintenanceList[$iterator]['images'] = array();
                    }
                    $assetMaintenanceList[$iterator]['date'] = date('l, d F Y',strtotime($assetMaintenance['created_at']));
                    $iterator++;
                }
                $message = "Success";
                $status = 200;
                $data['asset_maintenance_list'] = $assetMaintenanceList;
            }catch(\Exception $e){
                $message = "Fail";
                $status = 500;
                $data = [
                    'action' => 'Get Asset Request Maintenance Listing',
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

    public function generateGRN(){
        try{
            $currentDate = Carbon::now();
            $monthlyGrnGeneratedCount = GRNCount::where('month',$currentDate->month)->where('year',$currentDate->year)->pluck('count')->first();
            if ($monthlyGrnGeneratedCount != null) {
                GRNCount::where('month', $currentDate->month)->where('year', $currentDate->year)->update(['count' => (++$monthlyGrnGeneratedCount)]);
            } else {
                $monthlyGrnGeneratedCount = 1;
                GRNCount::create(['month' => $currentDate->month, 'year' => $currentDate->year, 'count' => 1]);
            }
            return "GRN".date('Ym').$monthlyGrnGeneratedCount;
        }catch(\Exception $e){
            $logData = [
                'action' => 'Generate GRN',
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($logData));
            return null;
        }
    }

        public function generateAssetMaintenanceRequestGRN(Request $request){
            try{
                $generatedGrn = $this->generateGRN();
                $assetMaintenance = AssetMaintenance::findOrFail($request->asset_maintenance_id);
                $grnGeneratedStatusId = AssetMaintenanceTransactionStatuses::where('slug','grn-generated')->pluck('id')->first();
                $assetMaintenanceTransactionData = [
                    'asset_maintenance_id' => $assetMaintenance->id,
                    'asset_maintenance_transaction_status_id' => $grnGeneratedStatusId,
                    'grn' => $generatedGrn
                ];
                $assetMaintenanceTransaction = AssetMaintenanceTransaction::create($assetMaintenanceTransactionData);
                if($request->has('images')){
                    $user = Auth::user();
                    $sha1UserId = sha1($user['id']);
                    $sha1assetMaintenanceId = sha1($assetMaintenance['id']);
                    $sha1assetMaintenanceTransactionId = sha1($assetMaintenanceTransaction['id']);
                    foreach ($request['images'] as $key1 => $imageName) {
                        $tempUploadFile = env('WEB_PUBLIC_PATH') . env('PRE_GRN_REQUEST_MAINTENANCE_TEMP_IMAGE_UPLOAD') . $sha1UserId . DIRECTORY_SEPARATOR . $imageName;
                        if (File::exists($tempUploadFile)) {
                            $imageUploadNewPath = env('WEB_PUBLIC_PATH') . env('ASSET_MAINTENANCE_REQUEST_IMAGE_UPLOAD') . $sha1assetMaintenanceId . DIRECTORY_SEPARATOR . 'bill_transaction' . DIRECTORY_SEPARATOR . $sha1assetMaintenanceTransactionId;
                            if (!file_exists($imageUploadNewPath)) {
                                File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                            }
                            $imageUploadNewPath .= DIRECTORY_SEPARATOR . $imageName;
                            File::move($tempUploadFile, $imageUploadNewPath);
                            AssetMaintenanceTransactionImages::create(['name' => $imageName, 'asset_maintenance_transaction_id' => $assetMaintenanceTransaction['id'],'is_pre_grn' => true]);
                        }
                    }
                }
                $message = "Success";
                $status = 200;
            }catch (\Exception $e){
                $generatedGrn = '';
                $message = "Fail";
                $status = 500;
                $data = [
                    'action' => 'Generate Asset Request Maintenance PRE-GRN',
                    'params' => $request->all(),
                    'exception' => $e->getMessage()
                ];
                Log::critical(json_encode($data));
            }
            $response = [
                'message' => $message,
                'grn_generated' => $generatedGrn
            ];
            return response()->json($response,$status);
        }

    public function createTransaction(Request $request){
        try{
            $assetMaintenanceTransaction = AssetMaintenanceTransaction::where('grn',$request['grn'])->first();
            $assetMaintenanceTransactionData = $request->only('bill_number','bill_amount',' remark');
            $assetMaintenanceTransactionData['asset_maintenance_transaction_status_id'] = AssetMaintenanceTransactionStatuses::where('slug','bill-pending')->pluck('id')->first();
            $assetMaintenanceTransactionData['in_time'] = $assetMaintenanceTransactionData['out_time'] = Carbon::now();
            $assetMaintenanceTransaction->update($assetMaintenanceTransactionData);
            if($request->has('images')){
                $user = Auth::user();
                $sha1UserId = sha1($user['id']);
                $sha1assetMaintenanceId = sha1($assetMaintenanceTransaction['asset_maintenance_id']);
                $sha1assetMaintenanceTransactionId = sha1($assetMaintenanceTransaction['id']);
                foreach ($request['images'] as $key => $imageName) {
                    $tempUploadFile = env('WEB_PUBLIC_PATH') . env('POST_GRN_REQUEST_MAINTENANCE_TEMP_IMAGE_UPLOAD') . $sha1UserId . DIRECTORY_SEPARATOR . $imageName;
                    if (File::exists($tempUploadFile)) {
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH') . env('ASSET_MAINTENANCE_REQUEST_IMAGE_UPLOAD') . $sha1assetMaintenanceId . DIRECTORY_SEPARATOR . 'bill_transaction' . DIRECTORY_SEPARATOR . $sha1assetMaintenanceTransactionId;
                        if (!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR . $imageName;
                        File::move($tempUploadFile, $imageUploadNewPath);
                        AssetMaintenanceTransactionImages::create(['name' => $imageName, 'asset_maintenance_transaction_id' => $assetMaintenanceTransaction['id'],'is_pre_grn' => false]);
                    }
                }
            }
            $status = 200;
            $message = "Transaction created Successfully";
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Create Asset Maintenance Transaction',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            abort(500);
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response,$status);
    }


}