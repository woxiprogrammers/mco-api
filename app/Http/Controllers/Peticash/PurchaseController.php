<?php

namespace App\Http\Controllers\Peticash;
use App\Asset;
use App\Material;
use App\MaterialRequestComponentTypes;
use App\PeticashTransactionType;
use App\PurchasePeticashTransaction;
use App\PurchasePeticashTransactionImage;
use App\Quotation;
use App\QuotationMaterial;
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
            $message = "Success";
            $status = 200;
            $purchaseTransaction = $request->except('source_slug','token');
            $purchaseTransaction['reference_user_id'] = $user['id'];
            $componentTypeSlug = MaterialRequestComponentTypes::where('id',$request['component_type_id'])->pluck('slug')->first();
            $materialId = Material::where('name',$request['name'])->pluck('id')->first();
            switch ($componentTypeSlug){
                case 'quotation-material':
                        $quotationId = Quotation::where('project_site_id',$request['project_site_id'])->pluck('id')->first();
                        $purchaseTransaction['reference_id'] = QuotationMaterial::where('quotation_id',$quotationId)->where('material_id',$materialId)->pluck('id')->first();
                    break;

                case 'structure-material':
                        $purchaseTransaction['reference_id'] = $materialId;
                    break;

                case 'system-asset':
                    $purchaseTransaction['reference_id'] = Asset::where('name',$request['name'])->pluck('id')->first();
                    break;
            }
            $purchaseTransaction['reference_id'] = $user['id'];
            $purchaseTransaction['peticash_transaction_type_id']= PeticashTransactionType::where('slug',$request['source_slug'])->where('type','PURCHASE')->pluck('id')->first();
            $purchaseTransactionId = PurchasePeticashTransaction::create($purchaseTransaction);
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
        }catch (\Exception $e){
            $message = $e->getMessage();
            $status = 500;
            $data = [
                'action' => 'Create Purchase',
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
}