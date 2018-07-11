<?php

namespace App\Http\Controllers\Inventory;
use App\Asset;
use App\FuelAssetReading;
use App\Http\Controllers\CustomTraits\InventoryTrait;
use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\InventoryComponent;
use App\InventoryComponentTransferImage;
use App\InventoryComponentTransfers;
use App\InventoryComponentTransferStatus;
use App\InventoryTransferTypes;
use App\Unit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Mockery\Exception;


class AssetManagementController extends BaseController
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    use InventoryTrait;
    use NotificationTrait;
    public function getAssetListing(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $pageId = $request->page;
            $projectSiteId = $request->project_site_id;
            $inventoryComponents = InventoryComponent::where('is_material',((boolean)false))->where('project_site_id',$projectSiteId)->get();
            $approvedStatusId = InventoryComponentTransferStatus::where('slug','approved')->pluck('id')->first();
            $inventoryListingData = array();
            $iterator = 0;
            $inTransferIds = InventoryTransferTypes::where('type','ilike','in')->pluck('id')->toArray();
            $outTransferIds = InventoryTransferTypes::where('type','ilike','out')->pluck('id')->toArray();
            foreach($inventoryComponents as $key => $inventoryComponent){
                $outQuantity = InventoryComponentTransfers::where('inventory_component_id', $inventoryComponent['id'])->where('inventory_component_transfer_status_id',$approvedStatusId)->whereIn('transfer_type_id',$outTransferIds)->sum('quantity');
                $inQuantity = InventoryComponentTransfers::where('inventory_component_id', $inventoryComponent['id'])->where('inventory_component_transfer_status_id',$approvedStatusId)->whereIn('transfer_type_id',$inTransferIds)->sum('quantity');
                $availableQuantity = $inQuantity - $outQuantity;
                if($availableQuantity > 0 || true){
                    $inventoryListingData[$iterator]['assets_name'] = $inventoryComponent['name'];
                    $inventoryListingData[$iterator]['asset_id'] = $inventoryComponent->asset->id;
                    $inventoryListingData[$iterator]['inventory_component_id'] = $inventoryComponent['id'];
                    if($inventoryComponent['reference_id'] == null || $inventoryComponent['reference_id'] == ''){
                        $inventoryListingData[$iterator]['model_number'] = '';
                        $inventoryListingData[$iterator]['assets_units'] = -1;
                        $inventoryListingData[$iterator]['total_work_hour'] = -1;
                        $inventoryListingData[$iterator]['total_diesel_consume'] = -1;
                        $inventoryListingData[$iterator]['litre_per_unit'] = -1;
                        $inventoryListingData[$iterator]['electricity_per_unit'] = -1;
                        $inventoryListingData[$iterator]['slug'] = '';
                        $inventoryListingData[$iterator]['total_electricity_consumed'] = -1;
                        $inventoryListingData[$iterator]['in'] = -1;
                        $inventoryListingData[$iterator]['out'] = -1;
                        $inventoryListingData[$iterator]['available'] = -1;
                    }else{
                        $inventoryListingData[$iterator]['model_number'] = $inventoryComponent->asset->model_number;
                        if($inventoryComponent->asset->assetTypes->slug != 'other'){
                            $readingInfo = FuelAssetReading::where('inventory_component_id',$inventoryComponent['id'])->get();
                            $assetUnits = 0;
                            $totalWorkHour = 0;
                            $totalDieselConsume = null;
                            $totalElectricityConsumed = null;
                            foreach ($readingInfo as $reading){
                                $assetUnits += (((int)$reading['stop_reading']) - ((int)$reading['start_reading']));
                                $startTime = Carbon::parse($reading['start_time']);
                                $endTime = Carbon::parse($reading['stop_time']);
                                $totalWorkHour += $endTime->diffInHours($startTime);
                                if($reading['fuel_per_unit'] != null){
                                    if($totalDieselConsume == null){
                                        $totalDieselConsume = 0;
                                    }
                                    $totalDieselConsume += ($reading['fuel_per_unit'] * ((((int)$reading['stop_reading']) - ((int)$reading['start_reading']))));
                                }
                                if($reading['electricity_per_unit'] != null){
                                    if($totalElectricityConsumed == null){
                                        $totalElectricityConsumed = 0;
                                    }
                                    $totalElectricityConsumed += ($reading['electricity_per_unit'] * ((((int)$reading['stop_reading']) - ((int)$reading['start_reading']))));
                                }
                            }
                            $inventoryListingData[$iterator]['assets_units'] = (float)$assetUnits;
                            $inventoryListingData[$iterator]['total_work_hour'] = (float)$totalWorkHour;
                            $inventoryListingData[$iterator]['total_diesel_consume'] = (float)$totalDieselConsume;
                            $inventoryListingData[$iterator]['litre_per_unit'] = (float)$inventoryComponent->asset->fuel_per_unit;
                            $inventoryListingData[$iterator]['electricity_per_unit'] = (float)$inventoryComponent->asset->electricity_per_unit;
                            $inventoryListingData[$iterator]['slug'] = $inventoryComponent->asset->assetTypes->slug;
                            $inventoryListingData[$iterator]['total_electricity_consumed'] = (float)$totalElectricityConsumed;
                            $inventoryListingData[$iterator]['in'] = -1;
                            $inventoryListingData[$iterator]['out'] = -1;
                            $inventoryListingData[$iterator]['available'] = -1;
                        }else{
                            $inventoryListingData[$iterator]['assets_units'] = -1;
                            $inventoryListingData[$iterator]['total_work_hour'] = -1;
                            $inventoryListingData[$iterator]['total_diesel_consume'] = -1;
                            $inventoryListingData[$iterator]['litre_per_unit'] = -1;
                            $inventoryListingData[$iterator]['electricity_per_unit'] = -1;
                            $inventoryListingData[$iterator]['slug'] = $inventoryComponent->asset->assetTypes->slug;
                            $inventoryListingData[$iterator]['total_electricity_consumed'] = -1;
                            $inventoryListingData[$iterator]['in'] = (float)$inQuantity;
                            $inventoryListingData[$iterator]['out'] = (float)$outQuantity;
                            $inventoryListingData[$iterator]['available'] = (float)$availableQuantity;
                        }
                    }
                    $iterator++;
                }
            }
            $data['assets_list'] = array();
            $displayLength = 30;
            $start = ((int)$pageId) * $displayLength;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalMaterialCount = count($inventoryListingData);
            $remainingCount = $totalMaterialCount - $totalSent;
            for($iterator = $start,$jIterator = 0; $iterator < $totalSent && $jIterator < $totalMaterialCount; $iterator++,$jIterator++){
                $data['assets_list'][] = $inventoryListingData[$iterator];
            }
            if($remainingCount > 0 ){
                $page_id = (string)($pageId + 1);
                $next_url = "/inventory/asset/listing";
            }else{
                $next_url = "";
                $page_id = "";
            }

        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Asset Listing',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            $next_url = "";
            $page_id = "";
            Log::critical(json_encode($data));
        }
        $response = [
            "data" => $data,
            "next_url" => $next_url,
            "page_id" => $page_id,
            "message" => $message,

        ];
        return response()->json($response,$status);
    }

    public function getSummaryAssetListing(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $inventoryComponent = InventoryComponent::where('id',$request->inventory_component_id)->first();
            $fuelAssetReadings = $inventoryComponent->fuelAssetReading;
            $asset = Asset::where('id',$inventoryComponent['reference_id'])->first();
            $summaryAssetListing = array();
            $iterator = 0;
            foreach($fuelAssetReadings as $key => $assetReading){
                $summaryAssetListing[$iterator]['id'] = $assetReading['id'];
                $summaryAssetListing[$iterator]['assets_units'] = (((int)$assetReading['stop_reading']) - ((int)$assetReading['start_reading']));
                $summaryAssetListing[$iterator]['work_hour_in_day'] = Carbon::parse($assetReading['stop_time'])->diffInHours(Carbon::parse($assetReading['start_time']));
                $summaryAssetListing[$iterator]['total_diesel_consume'] = ($asset['litre_per_unit'] * ((((int)$assetReading['stop_reading']) - ((int)$assetReading['start_reading']))));
                $summaryAssetListing[$iterator]['start_time'] = $assetReading['start_time'];
                $summaryAssetListing[$iterator]['stop_time'] = $assetReading['stop_time'];
                $summaryAssetListing[$iterator]['top_up_time'] = $assetReading['top_up_time'];
                $summaryAssetListing[$iterator]['fuel_remaining'] = null;
                $iterator++;
            }
            $data['assets_summary_list'] = $summaryAssetListing;
            $asset_name = $asset['name'];
            $next_url = "";
        }catch(\Exception $e){
            $message = "Fail";
            $next_url = $asset_name = "";
            $status = 500;
            $data = [
                'action' => 'Get Summary Asset Listing',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "data" => $data,
            "message" => $message,
            "next_url" => $next_url,
            "asset_name" => $asset_name
        ];
        return response()->json($response,$status);
    }

    /*public function createRequestMaintenance(Request $request){
        try{
            $status = 200;
            $data = $request->all();
            $user = Auth::user();
            if($request->has('remark')){
                $inventoryComponentTransfer['remark'] = $data['remark'];
            }
            $inventoryComponentTransfer['next_maintenance_hour'] = $data['next_maintenance_hour'];
            $inventoryComponentTransfer['user_id'] = $user['id'];
            $inventoryComponentTransfer['transfer_type_id'] = InventoryTransferTypes::where('slug','maintenance')->where('type','IN')->pluck('id')->first();
            $inventoryComponentTransfer['inventory_component_id'] = $data['inventory_component_id'];
            $inventoryComponentTransferId = InventoryComponentTransfers::insertGetId($inventoryComponentTransfer);
            if($request->has('image')){
                $sha1UserId = sha1($user['id']);
                $sha1MaterialRequestId = sha1($inventoryComponentTransferId);
                foreach($request['image'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('REQUEST_MAINTENANCE_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('REQUEST_MAINTENANCE_IMAGE_UPLOAD').$sha1MaterialRequestId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                        File::move($tempUploadFile,$imageUploadNewPath);
                        InventoryComponentTransferImage::create(['name' => $imageName,'inventory_component_transfer_id' => $inventoryComponentTransferId]);
                    }
                }
            }
            $message = "Maintenance Request Sent Successfully";
        }catch(Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Create Request Maintenance',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message
        ];
        return response()->json($response,$status);
    }*/

    public function addReadings(Request $request){
        try{
            $user = Auth::user();
            $message = "Asset Reading added successfully !!";
            $status = 200;
            $data = $request->except(['token']);
            $todayDate = date('Y-m-d');
            $approvedStatusId = InventoryComponentTransferStatus::where('slug','approved')->pluck('id')->first();
            $inventoryComponent = InventoryComponent::findOrFail($data['inventory_component_id']);
            $data['start_time'] = Carbon::createFromFormat('Y-m-d H:i:s',$todayDate.' '.$data['start_time']);
            $data['stop_time'] = Carbon::createFromFormat('Y-m-d H:i:s',$todayDate.' '.$data['stop_time']);
            if(array_key_exists('top_up_time',$data) && $data['top_up_time'] != null && $data['top_up_time'] != ''){
                $data['top_up_time'] =  Carbon::createFromFormat('Y-m-d H:i:s',$todayDate.' '.$data['top_up_time']);
            }else{
                $data['top_up_time'] = null;
            }
            if(array_key_exists('top_up',$data) && $data['top_up'] != null && $data['top_up'] != ''){
                $inTransferIds = InventoryTransferTypes::where('type','ilike','IN')->pluck('id')->toArray();
                $outTransferIds = InventoryTransferTypes::where('type','ilike','OUT')->pluck('id')->toArray();
                $dieselcomponentId = InventoryComponent::join('materials','materials.id','=','inventory_components.reference_id')
                    ->where('inventory_components.project_site_id',$inventoryComponent->project_site_id)
                    ->where('materials.slug','diesel')
                    ->pluck('inventory_components.id')
                    ->first();
                if($dieselcomponentId == null){
                    $response = [
                        "message" => 'Diesel is not assigned to this site. Please add diesel in inventory first.',
                        "is_fuel_limit_exceeded" => true
                    ];
                    return response()->json($response,203);
                }
                $inQuantity = InventoryComponentTransfers::where('inventory_component_id',$dieselcomponentId)->where('inventory_component_transfer_status_id',$approvedStatusId)->whereIn('transfer_type_id',$inTransferIds)->sum('quantity');
                $outQuantity = InventoryComponentTransfers::where('inventory_component_id',$dieselcomponentId)->where('inventory_component_transfer_status_id',$approvedStatusId)->whereIn('transfer_type_id',$outTransferIds)->sum('quantity');
                $availableQuantity = ($inQuantity + $inventoryComponent['opening_stock']) - $outQuantity;
                if($availableQuantity < $data['top_up']){
                    $response = [
                        "message" => 'Diesel Top-up quantity is more than available quantity. Available diesel is '.$availableQuantity.' litre.',
                        "is_fuel_limit_exceeded" => true
                    ];
                    return response()->json($response,203);
                }
            }else{
                $data['top_up'] = null;
            }
            $fuelAssetReading = FuelAssetReading::create($data);
            if(array_key_exists('top_up',$data)  && $data['top_up'] != null && $data['top_up'] != ''){
                $inventoryTransferData = [
                    'inventory_component_id' => $dieselcomponentId,
                    'quantity' => $data['top_up'],
                    'unit_id' => Unit::where('slug','litre')->pluck('id')->first(),
                    'source_name' => $user->first_name.' '.$user->last_name,
                    'user_id' => $user->id,
                    'inventory_component_transfer_status_id' => InventoryComponentTransferStatus::where('slug','approved')->pluck('id')->first()
                ];
                $this->create($inventoryTransferData,'user','OUT','from-api');
            }
        }catch(\Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Add asset Readings',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message,
            "is_fuel_limit_exceeded" => false
        ];
        return response()->json($response,$status);
    }

    public function readingListing(Request $request){
        try{
            $status = 200;
            $message = "Readings Listing successful";
            $data = array();
            if($request->has('date')){
                $readingsData = FuelAssetReading::where('inventory_component_id',$request->inventory_component_id)->whereDate('created_at',$request->date)->select('id','start_reading','stop_reading','start_time','stop_time','top_up_time','top_up','electricity_per_unit','fuel_per_unit')->orderBy('id','desc')->get();
                foreach($readingsData as $reading){
                    $reading = $reading->toArray();
                    $reading['start_time'] = date('H:i',strtotime($reading['start_time']));
                    $reading['stop_time'] = date('H:i',strtotime($reading['stop_time']));
                    $reading['date'] = $request->date;
                    if($reading['top_up_time'] != null){
                        $reading['top_up_time'] = date('H:i',strtotime($reading['top_up_time']));
                    }
                    $data[] = $reading;
                }
            }elseif($request->has('year') && $request->has('month')){
                $readingsData = FuelAssetReading::where('inventory_component_id',$request->inventory_component_id)->whereYear('created_at',$request->year)->whereMonth('created_at',$request->month)->orderBy('id','desc')->select('id','created_at')->get();
                foreach($readingsData as $reading){
                    $readingDate = date('Y-m-d',strtotime($reading->created_at));
                    if(!array_key_exists($readingDate,$data)){
                        $data[$readingDate] = [
                            'date' => $readingDate,
                            'total_working_hours' => 0,
                            'fuel_used' => -1,
                            'electricity_used' => -1,
                            'units_used' => 0,
                            'total_top_up' => -1
                        ];
                        $dayWiseReadingsData = FuelAssetReading::where('inventory_component_id',$request->inventory_component_id)->whereDate('created_at',$readingDate)->select('id','start_reading','stop_reading','start_time','stop_time','top_up_time','top_up','electricity_per_unit','fuel_per_unit')->orderBy('id','desc')->get();
                        foreach($dayWiseReadingsData as $dayWisereading){
                            $startTime = Carbon::parse($dayWisereading->start_time);
                            $stopTime = Carbon::parse($dayWisereading->stop_time);
                            $unitsWorked = (float)$dayWisereading['stop_reading'] - $dayWisereading['start_reading'];
                            $data[$readingDate]['total_working_hours'] += (float)$stopTime->diffInHours($startTime);
                            $data[$readingDate]['units_used'] += (float)$unitsWorked;
                            if($dayWisereading['electricity_per_unit'] != null && $dayWisereading['electricity_per_unit'] != ''){
                                if($data[$readingDate]['electricity_used'] == -1){
                                    $data[$readingDate]['electricity_used'] = 0;
                                }
                                $data[$readingDate]['electricity_used'] += (float)$dayWisereading['electricity_per_unit'] * $unitsWorked;
                            }
                            if($dayWisereading['fuel_per_unit'] != null && $dayWisereading['fuel_per_unit'] != ''){
                                if($data[$readingDate]['fuel_used'] == -1){
                                    $data[$readingDate]['fuel_used'] = 0;
                                }
                                $data[$readingDate]['fuel_used'] += (float)$dayWisereading['fuel_per_unit'] * $unitsWorked;
                            }
                            if($dayWisereading['top_up'] != null && $dayWisereading['top_up'] != ''){
                                if($data[$readingDate]['total_top_up'] == -1){
                                    $data[$readingDate]['total_top_up'] = 0;
                                }
                                $data[$readingDate]['total_top_up'] += (float)$dayWisereading['top_up'];
                            }
                        }
                    }
                }
            }
        }catch(\Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Add asset Readings',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message,
            "inventory_component_id" => (integer)$request->inventory_component_id,
            "data" => array_values($data)
        ];
        return response()->json($response,$status);
    }
}