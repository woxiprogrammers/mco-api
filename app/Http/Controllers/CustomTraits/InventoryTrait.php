<?php

namespace App\Http\Controllers\CustomTraits;

use App\GRNCount;
use App\InventoryComponentTransferImage;
use App\InventoryComponentTransfers;
use App\InventoryTransferTypes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

trait InventoryTrait{
    public function createInventoryTransfer(Request $request){
        try{
            $requestData = $request->except('name','type','token','images');
            $selectedTransferType = $this->create($requestData,$request['name'],$request['type'],'from-api',$request['images']);
            $message = "Inventory Component moved ".strtolower($selectedTransferType)." successfully";
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
    }

    public function create($request,$name,$type,$slug,$images=null){
        try{
            $inventoryComponentTransferData = $request;
            $selectedTransferType = InventoryTransferTypes::where('slug',$name)->where('type','ilike',$type)->first();
            $inventoryComponentTransferData['transfer_type_id'] = $selectedTransferType->id;
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
            $inventoryComponentTransferData['created_at'] = $inventoryComponentTransferData['updated_at'] = Carbon::now();
            $inventoryComponentTransferDataId = InventoryComponentTransfers::insertGetId($inventoryComponentTransferData);
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
        if($slug == 'from-api'){
            return $selectedTransferType->type;
        }else{
            return $inventoryComponentTransferDataId;
        }

    }
}