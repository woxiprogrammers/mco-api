<?php
/**
 * Created by Ameya Joshi.
 * Date: 27/12/17
 * Time: 1:25 PM
 */

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\MaterialRequestComponentHistory;
use App\MaterialRequests;
use App\Project;
use App\ProjectSite;
use App\PurchaseRequests;
use App\UserHasRole;
use App\UserLastLogin;
use App\UserProjectSiteRelation;
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

    public function getNotificationCounts(Request $request){
        try{
            $status = 200;
            $response = array();
            $user = Auth::user();
            $materialRequestCreateCount = $materialRequestDisapprovedCount = 0;
            $purchaseRequestCreateCount = $purchaseRequestDisapprovedCount = 0;
            if(!in_array($user->roles[0]->role->slug, ['admin','superadmin'])){
                if($user->customHasPermission('approve-material-request')){
                    $materialRequestCreateCount = MaterialRequests::join('material_request_components','material_requests.id','=','material_request_components.material_request_id')
                        ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_components.component_status_id')
                        ->join('user_project_site_relation','user_project_site_relation.project_site_id','=','material_requests.project_site_id')
                        ->where('user_project_site_relation.user_id',$user->id)
                        ->where('purchase_request_component_statuses.slug','pending')
                        ->where('material_requests.project_site_id', $request->project_site_id)
                        ->count();
                }
                if($user->customHasPermission('approve-purchase-request')){
                    $purchaseRequestCreateCount = PurchaseRequests::join('purchase_request_components','purchase_request_components.purchase_request_id','=','purchase_requests.id')
                        ->join('material_request_components','purchase_request_components.material_request_component_id','=','material_request_components.id')
                        ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_components.component_status_id')
                        ->join('user_project_site_relation','user_project_site_relation.project_site_id','=','purchase_requests.project_site_id')
                        ->where('user_project_site_relation.user_id',$user->id)
                        ->where('purchase_request_component_statuses.slug','p-r-assigned')
                        ->where('purchase_requests.project_site_id', $request->project_site_id)
                        ->count();
                }
                if($user->customHasPermission('create-material-request') || $user->customHasPermission('approve-material-request')){
                    $lastLogin = UserLastLogin::join('modules','modules.id','=','user_last_logins.module_id')
                        ->where('modules.slug','material-request')
                        ->where('user_last_logins.user_id',$user->id)
                        ->pluck('user_last_logins.last_login')
                        ->first();
                    if($lastLogin == null){
                        $materialRequestDisapprovedCount = MaterialRequests::join('material_request_components','material_requests.id','=','material_request_components.material_request_id')
                            ->join('material_request_component_history_table','material_request_component_history_table.material_request_component_id','=','material_request_components.id')
                            ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_components.component_status_id')
                            ->whereIn('purchase_request_component_statuses.slug',['manager-disapproved','admin-disapproved'])
                            ->where('material_requests.on_behalf_of',$user->id)
                            ->where('material_requests.project_site_id', $request->project_site_id)
                            ->count('material_request_components.id');
                    }else{
                        $materialRequestDisapprovedCount = MaterialRequests::join('material_request_components','material_requests.id','=','material_request_components.material_request_id')
                            ->join('material_request_component_history_table','material_request_component_history_table.material_request_component_id','=','material_request_components.id')
                            ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_components.component_status_id')
                            ->whereIn('purchase_request_component_statuses.slug',['manager-disapproved','admin-disapproved'])
                            ->where('material_requests.on_behalf_of',$user->id)
                            ->where('material_requests.project_site_id', $request->project_site_id)
                            ->where('material_request_component_history_table.created_at','>=',$lastLogin)
                            ->count('material_request_component_history_table.id');
                    }
                }
                if($user->customHasPermission('create-material-request') || $user->customHasPermission('approve-material-request') || $user->customHasPermission('create-purchase-request') || $user->customHasPermission('approve-purchase-request')){
                    $lastLogin = UserLastLogin::join('modules','modules.id','=','user_last_logins.module_id')
                        ->where('modules.slug','purchase-request')
                        ->where('user_last_logins.user_id',$user->id)
                        ->pluck('user_last_logins.last_login')
                        ->first();
                    if($lastLogin == null){
                        $purchaseRequestDisapprovedCount = MaterialRequests::join('material_request_components','material_requests.id','=','material_request_components.material_request_id')
			                ->join('purchase_request_components','purchase_request_components.material_request_component_id','=','material_request_components.id')
                            ->join('purchase_requests','purchase_requests.id','=','purchase_request_components.purchase_request_id')
                            ->join('material_request_component_history_table','material_request_component_history_table.material_request_component_id','=','material_request_components.id')
                            ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_components.component_status_id')
                            ->whereIn('purchase_request_component_statuses.slug',['manager-disapproved','admin-disapproved'])
                            ->where(function($query) use ($user){
                                $query->where('material_requests.on_behalf_of',$user->id)
                                    ->orWhere('purchase_requests.behalf_of_user_id',$user->id);
                            })
                            ->where('material_requests.project_site_id', $request->project_site_id)
                            ->count('purchase_request_components.id');
                    }else{
                        $purchaseRequestDisapprovedCount = MaterialRequests::join('material_request_components','material_requests.id','=','material_request_components.material_request_id')
                            ->join('purchase_request_components','purchase_request_components.material_request_component_id','=','material_request_components.id')
                            ->join('purchase_requests','purchase_requests.id','=','purchase_request_components.purchase_request_id')
                            ->join('material_request_component_history_table','material_request_component_history_table.material_request_component_id','=','material_request_components.id')
                            ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_components.component_status_id')
                            ->whereIn('purchase_request_component_statuses.slug',['p-r-manager-disapproved','p-r-admin-disapproved'])
                            ->where(function($query) use ($user){
                                $query->where('material_requests.on_behalf_of',$user->id)
                                    ->orWhere('purchase_requests.behalf_of_user_id',$user->id);
                            })
                            ->where('material_request_component_history_table.created_at','>=',$lastLogin)
                            ->where('material_requests.project_site_id', $request->project_site_id)
                            ->count();
                    }
                }
            }
            $response['data'] = [
                'material_request_create_count' => $materialRequestCreateCount,
                'material_request_disapproved_count' => $materialRequestDisapprovedCount,
                'purchase_request_create_count' => $purchaseRequestCreateCount,
                'purchase_request_disapproved_count' => $purchaseRequestDisapprovedCount,
            ];
        }catch(\Exception $e){
            $data = [
                'action' => 'Get Notification Count',
                'user' => Auth::user(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $status = 500;
            $response = array();
        }
        return response()->json($response,$status);
    }

    public function getProjectSiteWiseCount(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $user = Auth::user();
            $userProjectSiteIds = UserProjectSiteRelation::where('user_id',$user['id'])->pluck('project_site_id');
            $userRoleRelation = UserHasRole::where('user_id',$user['id'])->first();
            $userRole = $userRoleRelation->role->slug;
            if($userRole == 'admin' || $userRole == 'superadmin'){
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
                    $projects[$kIterator]['notification_count'] = 0;
                    $kIterator++;
                }
            }
            $data['projects'] = $projects;

        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Notification Count',
                'user' => Auth::user(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message,
            "data" => $data,
        ];
        return response()->json($response,$status);
    }
}
