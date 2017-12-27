<?php
/**
 * Created by Ameya Joshi.
 * Date: 27/12/17
 * Time: 1:25 PM
 */

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\CustomTraits\NotificationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;


class NotificationController extends BaseController{

    use NotificationTrait;
    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function storeFCMToken(Request $request){
        try{
            $response = array();
            $status = 200;
            $user = Auth::user();
            $user->update(['mobile_fcm_token' => $request->firebaseRefreshedToken]);
            $response['message'] = 'Token saved successfully';
        }catch(\Exception $e){
            $user = Auth::user();
            $data = [
                'action' => 'Store FCM token',
                'params' => $request->all(),
                'user' => $user,
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $status = 500;
            $response = [
                'message' => 'Something went wrong'
            ];
        }
        return response()->json($response,$status);
    }
}