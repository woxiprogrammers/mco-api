<?php
/**
 * Created by Ameya Joshi.
 * Date: 10/7/17
 * Time: 3:40 PM
 */
namespace App\Http\Controllers;

use App\Module;
use App\Project;
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
            if($request->has(['email','password'])){
                if( $token = JWTAuth::attempt($credentials) ){
                    $message = "Logged in successfully!!";
                    $status = 200;
                }else{
                    $token = null;
                    $message = "Invalid credentials";
                    $status = 401;
                    $data = null;
                }
            }elseif($request->has('token')){
//                dd(JWTAuth::parseToken()->authenticate());
                if (! $user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
                }else{
                    dd($user);
                }
                dd(JWTAuth::getToken());
                if(Auth::check()){
                    $message = "You are already logged in.";
                    $status = 200;
                }else{
                    $token = null;
                    $message = "Invalid credentials";
                    $status = 401;
                    $data = null;
                }
            }else{
                $status = 400;
                $message = "Invalid data";
                $token = null;
                $data = null;
            }
            $moduleResponse = array();
            $projects = null;
            if($status == 200){
                $user = Auth::user();
                $submoduleInfo = Module::join('permissions','permissions.module_id','=','modules.id')
                    ->join('user_has_permissions','user_has_permissions.permission_id','=','permissions.id')
                    ->where('user_has_permissions.user_id', $user->id)
                    ->select('modules.id as sub_module_id','modules.name as sub_module_name','modules.slug as sub_module_tag','permissions.id as permission_id','permissions.name as permission_name','modules.module_id as module_id')
                    ->get()
                    ->toArray();
                foreach ($submoduleInfo as $subModule){
                    if($subModule['module_id'] == null){
                        $subModule['module_id'] = $subModule['sub_module_id'];
                    }
                    if(!array_key_exists($subModule['module_id'],$moduleResponse)){
                        $moduleResponse[$subModule['module_id']] = array();
                        $moduleResponse[$subModule['module_id']]['id'] = $subModule['module_id'];
                        $moduleResponse[$subModule['module_id']]['module_name'] = Module::where('id', $subModule['module_id'])->pluck('name')->first();
                        $moduleResponse[$subModule['module_id']]['sub_modules'] = array();
                    }
                    if(!array_key_exists($subModule['sub_module_id'],$moduleResponse[$subModule['module_id']]['sub_modules'])){
                        $moduleResponse[$subModule['module_id']]['sub_modules'][$subModule['sub_module_id']]['id'] = $subModule['sub_module_id'];
                        $moduleResponse[$subModule['module_id']]['sub_modules'][$subModule['sub_module_id']]['sub_module_name'] = $subModule['sub_module_name'];
                        $moduleResponse[$subModule['module_id']]['sub_modules'][$subModule['sub_module_id']]['sub_module_tag'] = $subModule['sub_module_tag'];
                        $moduleResponse[$subModule['module_id']]['sub_modules'][$subModule['sub_module_id']]['permissions'] = array();
                    }
                    $moduleResponse[$subModule['module_id']]['sub_modules'][$subModule['sub_module_id']]['permissions'][$subModule['permission_id']]['can_access'] = $subModule['permission_name'];
                }
                $moduleResponse = array_values($moduleResponse);
                $iterator = 0;
                foreach ($moduleResponse as $module){
                    $moduleResponse[$iterator]['sub_modules'] = array_values($module['sub_modules']);
                    $jIterator = 0;
                    foreach ($moduleResponse[$iterator]['sub_modules'] as $subModule){
                        $moduleResponse[$iterator]['sub_modules'][$jIterator]['permissions'] = array_values($subModule['permissions']);
                        $jIterator++;
                    }
                    $iterator++;
                }
                $user = $user->toArray();
                unset($user['created_at']);
                unset($user['updated_at']);
                unset($user['role_id']);
                $data = $user;
                $projects = Project::where('is_active', true)->select('id','name')->get();
                if($projects  != null){
                    $projects = $projects->toArray();
                }
            }

            $response = [
                'data' => $data,
                'token' => $token,
                'message' => $message,
                'projects' => $projects,
                'modules' => $moduleResponse
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


