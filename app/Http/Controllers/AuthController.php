<?php
/**
 * Created by Ameya Joshi.
 * Date: 10/7/17
 * Time: 3:40 PM
 */
namespace App\Http\Controllers;

use App\Module;
use App\ProductVersion;
use App\Project;
use App\ProjectSite;
use App\Role;
use App\User;
use App\UserHasPermission;
use App\UserHasRole;
use App\UserProjectSiteRelation;
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
        $this->middleware('jwt.auth',['except' => ['login','getAppVersion']]);
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }
    public function logout(Request $request){
        try{
            $response = array();
            $status = 200;
            $user = Auth::user();
            $user->update(['mobile_fcm_token' => '']);
            $response['message'] = 'Token deleted successfully';
        }catch(\Exception $e){
            $user = Auth::user();
            $data = [
                'action' => 'FCM token deleted',
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
                    $userProjectSiteCount = UserProjectSiteRelation::where('user_id',$user['id'])->count();
                    if($userProjectSiteCount > 0){
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
                        $message = "Project Site not assigned";
                        $status = 404;
                    }

                }else{
                    $message = "Invalid credentials";
                    $status = 401;
                }
            }elseif($request->has(['mobile','password'])){
                $credentials = $request->only('mobile','password');
                if( $token = JWTAuth::attempt($credentials) ){
                    $user = Auth::user();
                    $userProjectSiteCount = UserProjectSiteRelation::where('user_id',$user['id'])->count();
                    if($userProjectSiteCount > 0){
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
                        $message = "Project Site not assigned";
                        $status = 404;
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
                $data = $this->getData($user);
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
            if($user->roles[0]->role->slug == 'admin' || $user->roles[0]->role->slug == 'superadmin'){
                $submoduleInfo = Module::join('permissions','permissions.module_id','=','modules.id')
                    ->where('permissions.is_mobile', true)
                    ->select('modules.id as sub_module_id','modules.name as sub_module_name','modules.slug as sub_module_tag','permissions.id as permission_id','permissions.name as permission_name','modules.module_id as module_id')
                    ->orderBy('sub_module_id')
                    ->distinct('permission_id')
                    ->get()
                    ->toArray();
            }else{
                $submoduleInfo = Module::join('permissions','permissions.module_id','=','modules.id')
                    ->join('user_has_permissions','user_has_permissions.permission_id','=','permissions.id')
                    ->where('user_has_permissions.user_id', $user->id)
                    ->where('user_has_permissions.is_mobile', true)
                    ->select('modules.id as sub_module_id','modules.name as sub_module_name','modules.slug as sub_module_tag','permissions.id as permission_id','permissions.name as permission_name','modules.module_id as module_id')
                    ->orderBy('sub_module_id')
                    ->distinct('permission_id')
                    ->get()
                    ->toArray();
            }
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
            $userRoleRelation = UserHasRole::where('user_id',$user['id'])->first();
            $userRole = $userRoleRelation->role;
            $userProjectSiteIds = UserProjectSiteRelation::where('user_id',$user['id'])->pluck('project_site_id');
            unset($user['created_at']);
            unset($user['updated_at']);
            unset($user['role_id']);
            $data = $user;
            $data['user_role'] = $userRole->name;
            $data['modules'] = $moduleResponse;
            if($userRole['slug'] == 'admin' || $userRole['slug'] == 'superadmin'){
                $projectIds = Project::join('project_sites','projects.id','=','project_sites.project_id')
                    ->where('projects.is_active',true)->select('projects.id')->get();
            }else{
                $projectIds = Project::join('project_sites','projects.id','=','project_sites.project_id')
                    ->where('projects.is_active',true)
                    ->whereIn('project_sites.id',$userProjectSiteIds)->select('projects.id')->get();
            }
            $projectSites = ProjectSite::whereIn('project_id',$projectIds)->get();
            $kIterator = 0;

            $projects = array();
            if($projectSites != null){
                foreach ($projectSites as $key1 => $projectSite) {
                    $projects[$kIterator]['project_site_id'] = $projectSite->id;
                    $projects[$kIterator]['project_site_name'] = $projectSite->name;
                    $projects[$kIterator]['project_site_address'] = $projectSite->name;
                    $project = $projectSite->project;
                    $projects[$kIterator]['project_id'] = $project->id;
                    $projects[$kIterator]['project_name'] = $project->name;
                    $projects[$kIterator]['client_company_name'] = $projectSite->project->client->company;
                    $kIterator++;
                }
            }
            usort($projects, function($a, $b) {
                return $a['project_name'] > $b['project_name'];
            });
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

    public function getAppVersion(Request $request){
        try{
            $status = 200;
            $response  = [
                'min_app_version' => env('MIN_APP_VERSION')
            ];
        }catch (\Exception $e){
            $data = [
                'action' => 'Get App Version',
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $status = 500;
            $response = null;
        }
        return response()->json($response, $status);
    }
}


