<?php

namespace App\Http\Controllers\Peticash;
use App\Asset;
use App\GRNCount;
use App\Material;
use App\MaterialRequestComponentTypes;
use App\PaymentType;
use App\PeticashStatus;
use App\PeticashTransactionType;
use App\PurchasePeticashTransaction;
use App\PurchasePeticashTransactionImage;
use App\Quotation;
use App\QuotationMaterial;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class PurchaseController extends BaseController{
    public function __construct(){
        $this->middleware('jwt.auth',['except' => ['autoSuggest']]);
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function createPurchase(Request $request){
        try{
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
                    break;
            }
            $purchaseTransaction['reference_id'] = $user['id'];
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
            $purchaseTransaction['peticash_status_id'] = PeticashStatus::where('slug','grn-generated')->pluck('id')->first();
            $purchaseTransaction['created_at'] = $purchaseTransaction['updated_at'] = Carbon::now();
            $purchaseTransactionId = PurchasePeticashTransaction::insertGetId($purchaseTransaction);
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
            Log::crtical(json_encode($data));
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
            if($request->has('reference_number')){
                PurchasePeticashTransaction::where('id',$request['peticash_transaction_id'])->update(['reference_number' => $request['reference_number']]);
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