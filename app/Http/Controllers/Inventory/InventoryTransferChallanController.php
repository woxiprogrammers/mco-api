<?php

namespace App\Http\Controllers\Inventory;

use App\GRNCount;
use App\Http\Controllers\CustomTraits\InventoryTrait;
use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\Http\Controllers\CustomTraits\UnitTrait;
use App\InventoryComponent;
use App\InventoryComponentOpeningStockHistory;
use App\InventoryComponentTransfers;
use App\InventoryComponentTransferStatus;
use App\InventoryTransferChallan;
use App\InventoryTransferTypes;
use App\ProjectSite;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class InventoryTransferChallanController extends BaseController
{
    use InventoryTrait;
    use UnitTrait;
    use NotificationTrait;
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if (!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function getPendingChallans($projectSiteId)
    {
        try {
            $approvedChallans = InventoryTransferChallan::join('inventory_component_transfers', 'inventory_component_transfers.inventory_transfer_challan_id', '=', 'inventory_transfer_challan.id')
                ->join('inventory_component_transfer_statuses', 'inventory_transfer_challan.inventory_component_transfer_status_id', '=', 'inventory_component_transfer_statuses.id')
                ->where('inventory_component_transfer_statuses.slug', 'open')
                //->whereNull('project_site_in_date')
                ->where('project_site_in_id', $projectSiteId)
                ->distinct('inventory_transfer_challan.id', 'inventory_transfer_challan.challan_number')
                ->select('inventory_transfer_challan.id', 'inventory_transfer_challan.challan_number')
                ->get()->toArray();
            $status = 200;
            $message = "Success";
        } catch (Exception $e) {
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Challan List',
                'params' => [],
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $approvedChallans = [];
        }
        $response = [
            "data"      => $approvedChallans,
            "message"   => $message,
        ];
        return response()->json($response, $status);
    }

    public function getChallanDetail($challanId)
    {
        try {
            $challan = InventoryTransferChallan::find($challanId);
            $outTransferType = InventoryTransferTypes::where('slug', 'site')->where('type', 'OUT')->first();
            $inventoryComponentOutTransfers = $outTransferType->inventoryComponentTransfers->where('inventory_transfer_challan_id', $challan['id']);
            foreach ($inventoryComponentOutTransfers as $outTransferComponent) {
                $siteInQuantity = '';
                if ($outTransferComponent['related_transfer_id'] != null) {
                    $inTransferComponent = InventoryComponentTransfers::find($outTransferComponent['related_transfer_id']);
                    $siteInQuantity = $inTransferComponent->quantity ?? '';
                }
                $components[] = [
                    'out_transfer_component_id' => $outTransferComponent['id'],
                    'name'                      => $outTransferComponent->inventoryComponent->name,
                    'is_material'               => $outTransferComponent->inventoryComponent->is_material,
                    'unit'                      => $outTransferComponent->unit->name ?? '',
                    'site_out_quantity'         => (string) $outTransferComponent->quantity ?? 0,
                    'in_transfer_component_id'  => $outTransferComponent['related_transfer_id'] ?? 0,
                    'site_in_quantity'          => (string) $siteInQuantity
                ];
            }
            $challan['inventory_component_transfer_status_name'] = $challan->inventoryComponentTransferStatus->name;
            $challan['items'] = $components;
            $challan['other_data'] = $challan->otherData()->toArray();
            $status = 200;
            $message = "Success";
        } catch (Exception $e) {
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Challan Detail',
                'params' => [],
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $challan = [];
        }
        $response = [
            "data"      => $challan,
            "message"   => $message,
        ];
        return response()->json($response, $status);
    }

    /**
     * Generate Site In pre GRN
     */
    public function generateSiteIn(Request $request, $challanID)
    {
        try {
            $user = Auth::user();
            //$updateChallanStatusToClose = true;
            $currentDate = Carbon::now();
            $challan = InventoryTransferChallan::find($challanID);
            if ($challan['project_site_in_date']) {
                return response()->json([
                    "message"   => 'Site in already done'
                ], 200);
            }
            $grnGeneratedStatusId = InventoryComponentTransferStatus::where('slug', 'grn-generated')->pluck('id')->first();
            $siteInTypeId = InventoryTransferTypes::where('slug', 'site')->where('type', 'ilike', 'IN')->pluck('id')->first();
            foreach ($request['items'] as $transferComponent) {
                $relatedInventoryComponentOutTransferData = InventoryComponentTransfers::where('id', $transferComponent['out_transfer_component_id'])->first();
                $outInventoryComponent = $relatedInventoryComponentOutTransferData->inventoryComponent;
                $projectSite = ProjectSite::where('id', $outInventoryComponent->project_site_id)->first();
                $sourceName = $projectSite->project->name . '-' . $projectSite->name;
                $monthlyGrnGeneratedCount = GRNCount::where('month', $currentDate->month)->where('year', $currentDate->year)->pluck('count')->first();
                if ($monthlyGrnGeneratedCount != null) {
                    $serialNumber = $monthlyGrnGeneratedCount + 1;
                } else {
                    $serialNumber = 1;
                }
                $grn = "GRN" . date('Ym') . ($serialNumber);
                $inInventoryComponent = InventoryComponent::where('project_site_id', $challan['project_site_in_id'])->where('reference_id', $outInventoryComponent['reference_id'])
                    ->where('is_material', $outInventoryComponent['is_material'])->first();
                if (!$inInventoryComponent) {
                    $inInventoryComponent = InventoryComponent::create([
                        'name'              => $outInventoryComponent['name'],
                        'project_site_id'   => $challan['project_site_in_id'],
                        'is_material'       => $outInventoryComponent['is_material'],
                        'reference_id'      => $outInventoryComponent['reference_id'],
                        'opening_stock'     => 0
                    ]);
                    InventoryComponentOpeningStockHistory::create([
                        'inventory_component_id' => $inInventoryComponent['id'],
                        'opening_stock' => $inInventoryComponent['opening_stock']
                    ]);
                }
                $inventoryComponentInTransfer = InventoryComponentTransfers::create([
                    'inventory_component_id'                    => $inInventoryComponent['id'],
                    'transfer_type_id'                          => $siteInTypeId,
                    'quantity'                                  => $transferComponent['site_in_quantity'],
                    'unit_id'                                   => $relatedInventoryComponentOutTransferData['unit_id'],
                    'source_name'                               => $sourceName,
                    'bill_number'                               => $relatedInventoryComponentOutTransferData['bill_number'],
                    'bill_amount'                               => $relatedInventoryComponentOutTransferData['bill_amount'],
                    'vehicle_number'                            => $relatedInventoryComponentOutTransferData['vehicle_number'],
                    'in_time'                                   => $currentDate,
                    'date'                                      => $currentDate,
                    'user_id'                                   => $user['id'],
                    'grn'                                       => $grn,
                    'inventory_component_transfer_status_id'    => $grnGeneratedStatusId,
                    'rate_per_unit'                             => $relatedInventoryComponentOutTransferData['rate_per_unit'],
                    'cgst_percentage'                           => $relatedInventoryComponentOutTransferData['cgst_percentage'],
                    'sgst_percentage'                           => $relatedInventoryComponentOutTransferData['sgst_percentage'],
                    'igst_percentage'                           => $relatedInventoryComponentOutTransferData['igst_percentage'],
                    'cgst_amount'                               => $relatedInventoryComponentOutTransferData['cgst_amount'],
                    'sgst_amount'                               => $relatedInventoryComponentOutTransferData['sgst_amount'],
                    'igst_amount'                               => $relatedInventoryComponentOutTransferData['igst_amount'],
                    'total'                                     => $relatedInventoryComponentOutTransferData['total'],
                    'vendor_id'                                 => $relatedInventoryComponentOutTransferData['vendor_id'],
                    'transportation_amount'                     => $relatedInventoryComponentOutTransferData['transportation_amount'],
                    'transportation_cgst_percent'               => $relatedInventoryComponentOutTransferData['transportation_cgst_percent'],
                    'transportation_sgst_percent'               => $relatedInventoryComponentOutTransferData['transportation_sgst_percent'],
                    'transportation_igst_percent'               => $relatedInventoryComponentOutTransferData['transportation_igst_percent'],
                    'driver_name'                               => $relatedInventoryComponentOutTransferData['driver_name'],
                    'mobile'                                    => $relatedInventoryComponentOutTransferData['mobile'],
                    'related_transfer_id'                       => $relatedInventoryComponentOutTransferData['id'],
                    'inventory_transfer_challan_id'             => $relatedInventoryComponentOutTransferData['inventory_transfer_challan_id']
                ]);

                $relatedInventoryComponentOutTransferData->update(['related_transfer_id' => $inventoryComponentInTransfer['id']]);
                if ($monthlyGrnGeneratedCount != null) {
                    GRNCount::where('month', $currentDate->month)->where('year', $currentDate->year)->update(['count' => $serialNumber]);
                } else {
                    GRNCount::create(['month' => $currentDate->month, 'year' => $currentDate->year, 'count' => $serialNumber]);
                }
                // if ($updateChallanStatusToClose && ($relatedInventoryComponentOutTransferData['quantity'] != $inventoryComponentInTransfer['quantity'])) {
                //     $updateChallanStatusToClose = false;
                // }
                $inventoryComponentTransferImages[] = [
                    'inventory_component_transfer_id'   => $inventoryComponentInTransfer['id'],
                ];
            }
            $challanUpdateData['project_site_in_date'] = $currentDate;
            // if ($updateChallanStatusToClose) {
            //     $challanUpdateData['inventory_component_transfer_status_id'] = InventoryComponentTransferStatus::where('slug', 'close')->pluck('id')->first();
            // }
            $challan->update($challanUpdateData);

            if ($request->has('images')) {
                $sha1UserId = sha1($user['id']);
                $sha1challanId = sha1($challan['id']);
                $imageUploadPath = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_IMAGE_UPLOAD');
                $newInUploadPath = $imageUploadPath . DIRECTORY_SEPARATOR . $sha1challanId . DIRECTORY_SEPARATOR . 'in';
                foreach ($request['images'] as $key1 => $imageName) {
                    $tempUploadFile = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_TEMP_IMAGE_UPLOAD') . $sha1UserId . DIRECTORY_SEPARATOR . $imageName;
                    if (File::exists($tempUploadFile)) {
                        if (!file_exists($newInUploadPath)) {
                            File::makeDirectory($newInUploadPath, $mode = 0777, true, true);
                        }
                        $fileFullPath = $newInUploadPath . DIRECTORY_SEPARATOR . $imageName;
                        File::move($tempUploadFile, $fileFullPath);
                        data_fill($inventoryComponentTransferImages, '*.name', $imageName);
                        data_fill($inventoryComponentTransferImages, '*.created_at', $currentDate);
                        data_fill($inventoryComponentTransferImages, '*.updated_at', $currentDate);
                        DB::table('inventory_component_transfer_images')->insert($inventoryComponentTransferImages);
                    }
                }
            }
            $response = [
                "message"   => 'Challan Site In successfully'
            ];
            $status = 200;
            return response()->json($response, $status);
        } catch (Exception $e) {
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Pre GRN site In',
                'params' => [
                    'request'   => $request->all(),
                    'challan_id'    => $challanID
                ],
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            return response()->json([
                "message"   => $message,
            ], $status);
        }
    }

    /**
     * Challan Site IN Post GRN generation
     */
    public function createSiteIn(Request $request, $challanId)
    {
        try {
            $user = Auth::user();
            $updateChallanStatusToClose = true;
            $now = Carbon::now();
            $challan = InventoryTransferChallan::find($challanId);
            $approvedStatusId = InventoryComponentTransferStatus::where('slug', 'approved')->pluck('id')->first();
            foreach ($request['items'] as $transferComponent) {
                $inventoryComponentInTransfer = InventoryComponentTransfers::find($transferComponent['in_transfer_component_id']);
                if ($inventoryComponentInTransfer) {
                    $inventoryComponentOutTransfer = InventoryComponentTransfers::find($inventoryComponentInTransfer['related_transfer_id']);
                    $inventoryComponentInTransfer->update([
                        'quantity'                              => $transferComponent['site_in_quantity'],
                        'out_time'                              => $now,
                        'date'                                  => $now,
                        'inventory_component_transfer_status_id' => $approvedStatusId,
                        'remark'                                => $request['remark'] ?? ''
                    ]);
                    if ($updateChallanStatusToClose && ($inventoryComponentOutTransfer['quantity'] != $inventoryComponentInTransfer['quantity'])) {
                        $updateChallanStatusToClose = false;
                    }
                    $inventoryComponentTransferImages[] = [
                        'inventory_component_transfer_id'   => $inventoryComponentInTransfer['id'],
                    ];

                    $webTokens = [$inventoryComponentOutTransfer->user->web_fcm_token];
                    $mobileTokens = [$inventoryComponentOutTransfer->user->mobile_fcm_token];
                    $notificationString = 'From ' . $inventoryComponentInTransfer->source_name . ' stock received to ';
                    $notificationString .= $inventoryComponentInTransfer->inventoryComponent->projectSite->project->name . ' - ' . $inventoryComponentInTransfer->inventoryComponent->projectSite->name . ' ';
                    $notificationString .= $inventoryComponentInTransfer->inventoryComponent->name . ' - ' . $inventoryComponentInTransfer->quantity . ' and ' . $inventoryComponentInTransfer->unit->name;
                    $this->sendPushNotification('Manisha Construction', $notificationString, $webTokens, $mobileTokens, 'c-m-s-i-t');
                }
            }
            if ($updateChallanStatusToClose) {
                $challanStatusSlug = 'close';
                $challanUpdateData['inventory_component_transfer_status_id'] = InventoryComponentTransferStatus::where('slug', $challanStatusSlug)->pluck('id')->first();
                $challan->update($challanUpdateData);
            }

            if ($request->has('images') && count($request->images) > 0) {
                $sha1UserId = sha1($user['id']);
                $sha1challanId = sha1($challan['id']);
                $imageUploadPath = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_IMAGE_UPLOAD');
                $newInUploadPath = $imageUploadPath . DIRECTORY_SEPARATOR . $sha1challanId . DIRECTORY_SEPARATOR . 'in';
                foreach ($request['images'] as $key1 => $imageName) {
                    $tempUploadFile = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_TEMP_IMAGE_UPLOAD') . $sha1UserId . DIRECTORY_SEPARATOR . $imageName;
                    if (File::exists($tempUploadFile)) {
                        if (!file_exists($newInUploadPath)) {
                            File::makeDirectory($newInUploadPath, $mode = 0777, true, true);
                        }
                        $fileFullPath = $newInUploadPath . DIRECTORY_SEPARATOR . $imageName;
                        File::move($tempUploadFile, $fileFullPath);
                        data_fill($inventoryComponentTransferImages, '*.name', $imageName);
                        data_fill($inventoryComponentTransferImages, '*.created_at', $now);
                        data_fill($inventoryComponentTransferImages, '*.updated_at', $now);
                        DB::table('inventory_component_transfer_images')->insert($inventoryComponentTransferImages);
                    }
                }
            }
            $message   = 'Challan Site In edited successfully';
            $status = 200;
        } catch (Exception $e) {
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Post GRN site In',
                'params' => [],
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $challan = [];
        }
        $response = [
            "message"   => $message,
        ];
        return response()->json($response, $status);
    }
}
