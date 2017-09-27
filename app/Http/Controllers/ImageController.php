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
            if($request['image_for'] == 'material-request'){
                $tempUploadPath = env('WEB_PUBLIC_PATH').env('MATERIAL_REQUEST_TEMP_IMAGE_UPLOAD');
                $tempImageUploadPath = $tempUploadPath.$sha1UserId;
            }elseif($request['image_for'] == 'request-maintenance'){
                $tempUploadPath = env('WEB_PUBLIC_PATH').env('REQUEST_MAINTENANCE_TEMP_IMAGE_UPLOAD');
                $tempImageUploadPath = $tempUploadPath.$sha1UserId;
            }
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