<?php
/**
 * Created by Ameya Joshi.
 * Date: 10/7/17
 * Time: 3:40 PM
 */
namespace App\Http\Controllers;

use App\Module;
use App\Project;
use App\UserHasPermission;
use Carbon\Carbon;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseController
{
    public function __construct()
    {
        $this->middleware('jwt.auth',['except' => ['login']]);
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }
    public function login(Request $request){
        try{
            $token = null;
            $message = null;
            $projects = null;
            $moduleResponse = null;
            $data = null;
            $loginDate = null;
            if($request->has(['email','password'])){
                $credentials = $request->only('email','password');
                if( $token = JWTAuth::attempt($credentials) ){
                    $user = Auth::user();
                    if($user['is_active'] == true){
                        $message = "Logged in successfully!!";
                        $status = 200;
                        $data = $this->getData($user);
                        $loginDate = Carbon::now();
                    }else{
                        $status = 401;
                        $message = "User is not activated yet. Please activate user first.";
                    }
                }else{
                    $message = "Invalid credentials";
                    $status = 401;
                }
            }elseif($request->has(['mobile','password'])){
                $credentials = $request->only('mobile','password');
                if( $token = JWTAuth::attempt($credentials) ){
                    $user = Auth::user();
                    if($user['is_active'] == true){
                        $message = "Logged in successfully!!";
                        $status = 200;
                        $data = $this->getData($user);
                        $loginDate = Carbon::now();
                    }else{
                        $status = 401;
                        $message = "User is not activated yet. Please activate user first.";
                    }
                }else{
                    $message = "Invalid credentials";
                    $status = 401;
                }
            }else{
                $status = 400;
                $message = "Invalid data";
            }
            $response = [
                'token' => $token,
                'message' => $message,
                'logged_in_at' => $loginDate,
                'data' => $data
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

    public function dashboard(Request $request){
        try{
            $status = null;
            $data = null;
            $token = null;
            $message = null;
            $projects = null;
            $moduleResponse = null;
            if(Auth::check()){
                $user = Auth::user();
                $message = "You are already logged in.";
                $status = 200;
                $newPermissions = UserHasPermission::where('user_id',$user->id)->where('created_at','>',$request->logged_in_at)->get();
                if(count($newPermissions) <= 0){
                    $status = 201;
                    $message = 'You have No New Permissions';
                    $data = null;
                }else{
                    $data = $this->getData($user);
                }
            }else{
                $token = null;
                $message = "Invalid credentials";
                $status = 401;
                $data = null;
            }
            $response = [
                'data' => $data,
                'token' => $request->token,
                'message' => $message
            ];
        }catch (\Exception $e){
            $data = [
                'action' => 'Dashboard',
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

    public function getData($user){
        try{
            $moduleResponse = array();
            $submoduleInfo = Module::join('permissions','permissions.module_id','=','modules.id')
                ->join('user_has_permissions','user_has_permissions.permission_id','=','permissions.id')
                ->where('user_has_permissions.user_id', $user->id)
                ->where('permissions.is_mobile', true)
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
            $data['modules'] = $moduleResponse;
            $projects = Project::where('is_active', true)->select('id','name')->get();
            if($projects  != null){
                $projects = $projects->toArray();
            }
            $data['projects'] = $projects;
            return $data;
        }catch (\Exception $e){
            $data = [
                'action' => 'Get Acls',
                'user' => $user,
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
    }
}


