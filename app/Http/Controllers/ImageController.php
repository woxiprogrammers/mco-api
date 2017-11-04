<?php
    /**
     * Created by Harsha.
     * Date: 27/9/17
     * Time: 11:33 AM
     */

namespace App\Http\Controllers;


use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class ImageController extends BaseController{

    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function saveImages(Request $request){
        try{
            $user = Auth::user();
            $sha1UserId = sha1($user['id']);
            switch ($request['image_for']){
                case 'material-request' :
                    $tempUploadPath = env('WEB_PUBLIC_PATH').env('MATERIAL_REQUEST_TEMP_IMAGE_UPLOAD');
                    break;

                case 'request-maintenance':
                    $tempUploadPath = env('WEB_PUBLIC_PATH').env('REQUEST_MAINTENANCE_TEMP_IMAGE_UPLOAD');
                    break;

                case 'bill_transaction' :
                    $tempUploadPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_BILL_TRANSACTION_TEMP_IMAGE_UPLOAD');
                    break;

                case 'bill_payment' :
                    $tempUploadPath = env('WEB_PUBLIC_PATH').env('PURCHASE_ORDER_BILL_PAYMENT_TEMP_IMAGE_UPLOAD');
                    break;

                case 'peticash_salary_transaction' :
                    $tempUploadPath = env('WEB_PUBLIC_PATH').env('PETICASH_SALARY_TRANSACTION_TEMP_IMAGE_UPLOAD');
                    break;

                case 'peticash_purchase_transaction' :
                    $tempUploadPath = env('WEB_PUBLIC_PATH').env('PETICASH_PURCHASE_TRANSACTION_TEMP_IMAGE_UPLOAD');
                    break;

                    case 'inventory_transfer' :
                    $tempUploadPath = env('WEB_PUBLIC_PATH').env('INVENTORY_TRANSFER_TEMP_IMAGE_UPLOAD');
                    break;

                default :
                    $tempUploadPath = '';
            }
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
                'action' => 'Save Images',
                'exception' => $e->getMessage(),
                'request' => $request->all()
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