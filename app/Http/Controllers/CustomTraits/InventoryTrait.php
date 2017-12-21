<?php

namespace App\Http\Controllers\CustomTraits;

use App\GRNCount;
use App\InventoryComponent;
use App\InventoryComponentTransferImage;
use App\InventoryComponentTransfers;
use App\InventoryComponentTransferStatus;
use App\InventoryTransferTypes;
use App\ProjectSite;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

trait InventoryTrait{
    public function createInventoryTransfer(Request $request){
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
            $inventoryComponentTransferData['in_time'] = $inventoryComponentTransferData['out_time'] = Carbon::now();
            $inventoryComponentTransfer = InventoryComponentTransfers::create($inventoryComponentTransferData);
            $inventoryComponentTransferDataId = $inventoryComponentTransfer->id;
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
            $inventoryTransferData = InventoryComponentTransfers::where('inventory_component_transfer_status_id',InventoryComponentTransferStatus::where('slug','requested')->pluck('id')->first())->orderBy('created_at','desc')->get();
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
}