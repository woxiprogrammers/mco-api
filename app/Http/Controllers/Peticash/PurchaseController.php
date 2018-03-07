<?php

namespace App\Http\Controllers\Peticash;
use App\Asset;
use App\AssetType;
use App\CategoryMaterialRelation;
use App\GRNCount;
use App\Http\Controllers\CustomTraits\InventoryTrait;
use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\InventoryComponent;
use App\InventoryComponentTransferImage;
use App\Material;
use App\MaterialRequestComponentTypes;
use App\PaymentType;
use App\PeticashSiteTransfer;
use App\PeticashStatus;
use App\PeticashTransactionType;
use App\PurchasePeticashTransaction;
use App\PurchasePeticashTransactionImage;
use App\Quotation;
use App\QuotationMaterial;
use App\QuotationStatus;
use App\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class PurchaseController extends BaseController{
use InventoryTrait;
use NotificationTrait;
    public function __construct(){
        $this->middleware('jwt.auth',['except' => ['autoSuggest']]);
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function createPurchase(Request $request){
        try{
            $now = Carbon::now();
            $user = Auth::user();
            $purchaseTransaction = $request->except('source_slug','token','images');
            $purchaseTransaction['reference_user_id'] = $user['id'];
            $componentTypeSlug = MaterialRequestComponentTypes::where('id',$request['component_type_id'])->pluck('slug')->first();
            $materialId = Material::where('name',$request['name'])->pluck('id')->first();
            switch ($componentTypeSlug){
                case 'quotation-material':
                        $quotationId = Quotation::where('project_site_id',$request['project_site_id'])->pluck('id')->first();
                        $purchaseTransaction['reference_id'] = QuotationMaterial::where('quotation_id',$quotationId)->where('material_id',$materialId)->pluck('id')->first();
                    break;

                case 'system-asset':
                    $purchaseTransaction['reference_id'] = Asset::where('name',$request['name'])->pluck('id')->first();
                    $purchaseTransaction['unit_id'] = Unit::where('slug','nos')->pluck('id')->first();
                    break;

                case 'new-asset' :
                    $purchaseTransaction['unit_id'] = Unit::where('slug','nos')->pluck('id')->first();
                    $data = $request['name'];
                    $purchaseTransaction['reference_id'] = $this->createMaterial($data,'new-asset');
                    break;

                case 'new-material' :
                    $data = $request->except('project_site_id','source_slug','source_name','component_type_id','date','payment_type_id','remark','in_time','out_time','vehicle_no','images');
                    $purchaseTransaction['reference_id'] = $this->createMaterial($data,'new-material');
            }
            $purchaseTransaction['reference_user_id'] = $user['id'];
            $currentDate = Carbon::now();
            $monthlyGrnGeneratedCount = GRNCount::where('month',$currentDate->month)->where('year',$currentDate->year)->pluck('count')->first();
            if($monthlyGrnGeneratedCount != null){
                $serialNumber = $monthlyGrnGeneratedCount + 1;
            }else{
                $serialNumber = 1;
            }
            $purchaseTransaction['grn'] = "GRN".date('Ym').($serialNumber);
            $purchaseTransaction['payment_type_id'] = PaymentType::where('slug','peticash')->pluck('id')->first();
            $purchaseTransaction['peticash_transaction_type_id']= PeticashTransactionType::where('slug',$request['source_slug'])->where('type','PURCHASE')->pluck('id')->first();
            $purchaseTransaction['peticash_status_id'] = PeticashStatus::where('slug','approved')->pluck('id')->first();
            $purchaseTransaction['in_time'] = $now;
            $purchaseTransactionData = PurchasePeticashTransaction::create($purchaseTransaction);
            $purchaseTransactionId = $purchaseTransactionData['id'];
            if($monthlyGrnGeneratedCount != null) {
                GRNCount::where('month', $currentDate->month)->where('year', $currentDate->year)->update(['count' => $serialNumber]);
            }else{
                GRNCount::create(['month'=> $currentDate->month, 'year'=> $currentDate->year,'count' => $serialNumber]);
            }
            if(array_has($request,'images')){
                $user = Auth::user();
                $sha1UserId = sha1($user['id']);
                $sha1PurchaseTransactionId = sha1($purchaseTransactionId);
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PETICASH_PURCHASE_TRANSACTION_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('PETICASH_PURCHASE_TRANSACTION_IMAGE_UPLOAD').$sha1PurchaseTransactionId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                        File::move($tempUploadFile,$imageUploadNewPath);
                        PurchasePeticashTransactionImage::create(['name' => $imageName,'purchase_peticash_transaction_id' => $purchaseTransactionId,'type' => 'bill']);
                    }
                }
            }

            $alreadyPresent = InventoryComponent::where('name','ilike',$purchaseTransactionData['name'])->where('project_site_id',$purchaseTransactionData['project_site_id'])->first();
            if($alreadyPresent != null){
                $inventoryComponentId = $alreadyPresent['id'];
            }else {
                if ($componentTypeSlug == 'quotation-material' || $componentTypeSlug == 'new-material' || $componentTypeSlug == 'structure-material') {
                    $inventoryData['is_material'] = true;
                    $inventoryData['reference_id'] = Material::where('name', 'ilike', $purchaseTransactionData['name'])->pluck('id')->first();
                } else {
                    $inventoryData['is_material'] = false;
                    $inventoryData['reference_id'] = Asset::where('name', 'ilike', $purchaseTransactionData['name'])->pluck('id')->first();
                }
                $inventoryData['name'] = $purchaseTransactionData['name'];
                $inventoryData['project_site_id'] = $purchaseTransactionData['project_site_id'];
                $inventoryData['opening_stock'] = 0;
                $inventoryComponent = InventoryComponent::create($inventoryData);
                $inventoryComponentId = $inventoryComponent->id;
            }
            $transferData['inventory_component_id'] = $inventoryComponentId;
            $name = $request['source_slug'];
            $transferData['quantity'] = $purchaseTransactionData['quantity'];
            $transferData['unit_id'] = $purchaseTransactionData['unit_id'];
            $transferData['date'] = $purchaseTransactionData['created_at'];
            $transferData['in_time'] = $now;
            $transferData['out_time'] = $now;
            $transferData['vehicle_number'] = $purchaseTransactionData['vehicle_number'];
            $transferData['bill_number'] = $purchaseTransactionData['bill_number'];
            $transferData['bill_amount'] = $purchaseTransactionData['bill_amount'];
            $transferData['remark'] = $purchaseTransactionData['remark'];
            $transferData['source_name'] = $purchaseTransactionData['source_name'];
            $transferData['grn'] = $purchaseTransactionData['grn'];
            $transferData['user_id'] = $user['id'];
            $createdTransferInId = $this->create($transferData,$name,'IN','from-purchase');
            if ($componentTypeSlug == 'quotation-material' || $componentTypeSlug == 'new-material' || $componentTypeSlug == 'structure-material') {
                $createdTransferOutId = $this->create($transferData, 'user', 'OUT', 'from-purchase');
                $sha1InventoryTransferOutId = sha1($createdTransferOutId);
            }
            $purchasePeticashTransactionImages = PurchasePeticashTransactionImage::where('purchase_peticash_transaction_id',$purchaseTransactionData['id'])->get();
            if(count($purchasePeticashTransactionImages) > 0){
                $sha1PurchaseTransactionId = sha1($purchaseTransactionData['id']);
                $sha1InventoryComponentId = sha1($inventoryComponentId);
                $sha1InventoryTransferInId = sha1($createdTransferInId);

                foreach ($purchasePeticashTransactionImages as $key => $images){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PETICASH_PURCHASE_TRANSACTION_IMAGE_UPLOAD').$sha1PurchaseTransactionId.DIRECTORY_SEPARATOR.$images['name'];

                    $imageUploadNewPathForInventoryIn = env('WEB_PUBLIC_PATH').env('INVENTORY_TRANSFER_IMAGE_UPLOAD').$sha1InventoryComponentId.DIRECTORY_SEPARATOR.'transfers'.DIRECTORY_SEPARATOR.$sha1InventoryTransferInId;
                    if(!file_exists($imageUploadNewPathForInventoryIn)) {
                        File::makeDirectory($imageUploadNewPathForInventoryIn, $mode = 0777, true, true);
                    }
                    $imageUploadNewPathForInventoryIn .= DIRECTORY_SEPARATOR.$images['name'];
                    File::copy($tempUploadFile,$imageUploadNewPathForInventoryIn);
                    InventoryComponentTransferImage::create(['name' => $images['name'],'inventory_component_transfer_id' => $createdTransferInId]);

                    if ($componentTypeSlug == 'quotation-material' || $componentTypeSlug == 'new-material' || $componentTypeSlug == 'structure-material') {
                        $imageUploadNewPathForInventoryOut = env('WEB_PUBLIC_PATH') . env('INVENTORY_TRANSFER_IMAGE_UPLOAD') . $sha1InventoryComponentId . DIRECTORY_SEPARATOR . 'transfers' . DIRECTORY_SEPARATOR . $sha1InventoryTransferOutId;
                        if (!file_exists($imageUploadNewPathForInventoryOut)) {
                            File::makeDirectory($imageUploadNewPathForInventoryOut, $mode = 0777, true, true);
                        }
                        $imageUploadNewPathForInventoryOut .= DIRECTORY_SEPARATOR.$images['name'];
                        File::copy($tempUploadFile,$imageUploadNewPathForInventoryOut);
                        InventoryComponentTransferImage::create(['name' => $images['name'],'inventory_component_transfer_id' => $createdTransferInId]);
                    }
                }
            }
            $data = array();
            $data['payable_amount'] = $request['bill_amount'];
            $data['peticash_transaction_id'] = $purchaseTransactionId;
            $data['grn'] = $purchaseTransaction['grn'];
            $message = "Purchase Peticash created Successfully";
            $status = 200;
        }catch (\Exception $e){
            $message = $e->getMessage();
            $status = 500;
            $data = [
                'action' => 'Create Purchase Peticash',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
            'data' => $data
        ];
        return response()->json($response,$status);
    }

    public function createMaterial($data,$componentTypeSlug){
        try{
            $now = Carbon::now();
            if($componentTypeSlug == 'new-material') {
                $materialData['name'] = ucwords(trim($data['name']));
                $categoryMaterialData['category_id'] = $data['miscellaneous_category_id'];
                $materialData['rate_per_unit'] = round(($data['bill_amount'] / $data['quantity']),3);
                $materialData['unit_id'] = $data['unit_id'];
                $materialData['is_active'] = (boolean)1;
                $material = Material::create($materialData);
                $categoryMaterialData['material_id'] = $material['id'];
                CategoryMaterialRelation::create($categoryMaterialData);
                $approvedQuotationIds = Quotation::where('quotation_status_id', QuotationStatus::where('slug','approved')->pluck('id')->first())->pluck('id');
                foreach ($approvedQuotationIds as $quotationId) {
                    $quotationMaterialData = array();
                    $quotationMaterialData['material_id'] = $material['id'];
                    $quotationMaterialData['rate_per_unit'] = round(($data['bill_amount'] / $data['quantity']),3);
                    $quotationMaterialData['unit_id'] = $data['unit_id'];
                    $quotationMaterialData['quantity'] = $data['quantity'];
                    $quotationMaterialData['is_client_supplied'] = false;
                    $quotationMaterialData['created_at'] = $now;
                    $quotationMaterialData['updated_at'] = $now;
                    $quotationMaterialData['quotation_id'] = $quotationId;
                    QuotationMaterial::create($quotationMaterialData);
                }
                $reference_id = $material['id'];
            }elseif($componentTypeSlug == 'new-asset'){
                $assetData['name'] = ucwords(trim($data['name']));
                $assetData['is_active'] = true;
                $assetData['asset_types_id'] = AssetType::where('slug','other')->pluck('id')->first();
                $asset = Asset::create($assetData);
                $reference_id = $asset['id'];
            }
        }catch(\Exception $e){
            $data = [
                'action' => 'Create New Material/Asset',
                'exception' => $e->getMessage(),
                'params' => $data
            ];
            Log::critical(json_encode($data));
        }
        return $reference_id;
    }

    public function getTransactionDetails(Request $request){
        try{
            $purchaseTransactionData = PurchasePeticashTransaction::where('id',$request['peticash_transaction_id'])->first();
            $data['peticash_transaction_id'] = $purchaseTransactionData->id;
            $data['name'] = $purchaseTransactionData->name;
            $data['project_site_name'] = $purchaseTransactionData->projectSite->name;

            $data['grn'] = $purchaseTransactionData->grn;
            $data['date'] = date('l, d F Y',strtotime($purchaseTransactionData->date));
            $data['source_name'] = $purchaseTransactionData->source_name;
            $data['peticash_transaction_type'] = $purchaseTransactionData->peticashTransactionType->name;
            $data['component_type'] = $purchaseTransactionData->componentType->name;
            $data['quantity'] = $purchaseTransactionData->quantity;
            $data['unit_name'] = $purchaseTransactionData->unit->name;
            $data['bill_number'] = ($purchaseTransactionData->bill_number != null) ? $purchaseTransactionData->bill_number : '';
            $data['bill_amount'] = ($purchaseTransactionData->bill_amount != null) ? $purchaseTransactionData->bill_amount : '';
            $data['vehicle_number'] = ($purchaseTransactionData->vehicle_number != null) ? $purchaseTransactionData->vehicle_number : '';
            $data['in_time'] = ($purchaseTransactionData->in_time != null) ? $purchaseTransactionData->in_time : '';
            $data['out_time'] = ($purchaseTransactionData->out_time) ? $purchaseTransactionData->out_time : '';
            $data['reference_number'] = ($purchaseTransactionData->reference_number != null) ? $purchaseTransactionData->reference_number : '';
            $data['payment_type'] = $purchaseTransactionData->paymentType->name;
            $data['peticash_status_name'] = $purchaseTransactionData->peticashStatus->name;
            $data['remark'] = ($purchaseTransactionData->remark != null) ? $purchaseTransactionData->remark : '' ;
            $data['admin_remark'] = ($purchaseTransactionData->admin_remark == null) ? '' : $purchaseTransactionData->admin_remark;
            $transactionImages = PurchasePeticashTransactionImage::where('purchase_peticash_transaction_id',$purchaseTransactionData->id)->get();
            if(count($transactionImages) > 0){
                $data['list_of_images'] = $this->getUploadedImages($transactionImages,$purchaseTransactionData->id);
            }else{
                $data['list_of_images'][0]['image_url'] = null;
            }
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $message = $e->getMessage();
            $status = 500;
            $data = [
                'action' => 'Get Transaction Detail',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
            'data' => $data
        ];
        return response()->json($response,$status);
    }

    public function getUploadedImages($transactionImages,$transactionId){
        $iterator = 0;
        $images = array();
        $sha1PurchaseTransactionId = sha1($transactionId);
        $imageUploadPath = env('PETICASH_PURCHASE_TRANSACTION_IMAGE_UPLOAD').$sha1PurchaseTransactionId;
        foreach($transactionImages as $index => $image){
            $images[$iterator]['image_url'] = $imageUploadPath.DIRECTORY_SEPARATOR.$image->name;
            $iterator++;
        }
        return $images;
    }

    public function createBillPayment(Request $request){
        try{
            $now = Carbon::now();
            $purchasePeticashTransaction = PurchasePeticashTransaction::where('id',$request['peticash_transaction_id'])->first();
            if($request->has('reference_number')){
                $transactionData['reference_number'] = $request['reference_number'];
            }
            $transactionData['out_time'] = $now;
            $purchasePeticashTransaction->update($transactionData);
            $sitePeticashTransfers = PeticashSiteTransfer::where('project_site_id',$purchasePeticashTransaction['project_site_id'])->where('amount','>',0)->get();
            $remainingSalary = $purchasePeticashTransaction['bill_amount'];
            foreach ($sitePeticashTransfers as $peticashTransfer){
                if($peticashTransfer->amount < $remainingSalary){
                    $remainingSalary = $remainingSalary - $peticashTransfer->amount;
                    $peticashTransfer->update(['amount' => 0]);
                }elseif($peticashTransfer->amount >= $remainingSalary){
                    $peticashTransfer->update(['amount' => ($peticashTransfer->amount - $remainingSalary)]);
                    break;
                }
            }
            if(array_has($request,'images')){
                $user = Auth::user();
                $sha1UserId = sha1($user['id']);
                $sha1PurchaseTransactionId = sha1($request['peticash_transaction_id']);
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PETICASH_PURCHASE_PAYMENT_TRANSACTION_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('PETICASH_PURCHASE_TRANSACTION_IMAGE_UPLOAD').$sha1PurchaseTransactionId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                        File::move($tempUploadFile,$imageUploadNewPath);
                        PurchasePeticashTransactionImage::create(['name' => $imageName,'purchase_peticash_transaction_id' => $request['peticash_transaction_id'],'type' => 'payment']);
                    }
                }
            }
            $message = 'Data updated successfully';
            $status = 200;
        }catch (\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Create Bill payment',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response,$status);
    }


}