<?php
/**
 * Created by Ameya Joshi.
 * Date: 10/7/17
 * Time: 3:40 PM
 */
namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseController
{
    public function login(Request $request){
        try{
            $credentials = $request->only('email','password');
            if( $token = JWTAuth::attempt($credentials) ){
                $user = Auth::user()->toArray();
                unset($user['created_at']);
                unset($user['updated_at']);
                unset($user['role_id']);
                $data = $user;
                $message = "Logged in successfully!!";
                $status = 200;
            }else{
                $token = null;
                $message = "Invalid credentials";
                $status = 401;
                $data = null;
            }
            $response = [
                'data' => $data,
                'token' => $token,
                'message' => $message
            ];
        }catch (\Exception $e){
            $data = [
                'action' => 'Login',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $response = [
                'message' => 'Something went wrong'
            ];
            $status = 500;
        }
        return response()->json($response,$status);
    }
}


