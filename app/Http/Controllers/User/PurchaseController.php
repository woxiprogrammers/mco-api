<?php

namespace App\Http\Controllers\User;
use App\MaterialRequests;
use App\Module;
use App\Permission;
use App\PurchaseRequestComponentStatuses;
use App\Role;
use App\User;
use App\UserHasPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class PurchaseController extends BaseController
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function getPurchaseRequestApprovalACl(Request $request){
        try{
            $authUser = Auth::user();
            $status = 200;
            $message = "Success";
            $approvalAclPermission = Permission::where('name',$request['can_access'])->first();
            $adminUserIds = Role::join('user_has_roles','user_has_roles.role_id','=','roles.id')
                ->whereIn('roles.slug',['admin','superadmin'])->pluck('user_has_roles.user_id');
            $userIds = UserHasPermission::join('user_project_site_relation','user_project_site_relation.user_id','=','user_has_permissions.user_id')
                ->where('user_project_site_relation.project_site_id','=',$request['project_site_id'])
                ->where('user_has_permissions.permission_id',$approvalAclPermission->id)
                ->whereNotIn('user_has_permissions.user_id',$adminUserIds)->pluck('user_has_permissions.user_id');
            $userIds = array_merge($adminUserIds->toArray(),$userIds->toArray());
            $users = User::whereIn('id',$userIds)->get();
            $i = 0;
            $available_users = array();
            foreach ($users as $key => $user){
                $available_users[$i]['id'] = $user->id;
                $available_users[$i]['user_name'] = $user->first_name." ".$user->last_name;
                $i++;
            }
            if($request['component_status_slug'] == 'in-indent'){
                $materialRequestAssigned = MaterialRequests::get();
            }else{
                $materialRequestAssigned = MaterialRequests::where('assigned_to',$authUser['id'])->get();
            }
            $materialRequestList = array();
            $requestedStatusId = PurchaseRequestComponentStatuses::where('slug',$request['component_status_slug'])->pluck('id')->first();
            $iterator = 0;
            foreach ($materialRequestAssigned as $key1 => $materialRequest){
                foreach($materialRequest->materialRequestComponents->where('component_status_id',$requestedStatusId) as $index => $materialRequestComponent){
                    $materialRequestList[$iterator]['material_request_component_id'] = $materialRequestComponent->id;
                    $materialRequestList[$iterator]['name'] = $materialRequestComponent->name;
                    $materialRequestList[$iterator]['quantity'] = $materialRequestComponent->quantity;
                    $materialRequestList[$iterator]['unit_id'] = $materialRequestComponent->unit_id;
                    $materialRequestList[$iterator]['unit'] = $materialRequestComponent->unit->name;
                    $materialRequestList[$iterator]['component_type_id'] = $materialRequestComponent->component_type_id;
                    $materialRequestList[$iterator]['component_type'] = $materialRequestComponent->materialRequestComponentTypes->name;
                    $materialRequestList[$iterator]['component_status_id'] = $materialRequestComponent->component_status_id;
                    $materialRequestList[$iterator]['component_status'] = $materialRequestComponent->purchaseRequestComponentStatuses->slug;
                    $materialRequestList[$iterator]['created_at'] = date($materialRequestComponent->created_at);
                    $iterator++;
                }
            }
        }catch(\Exception $e){
            $available_users = $materialRequestList = array();
            $data = [
                'action' => 'Get Purchase Request ACLs',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $status = 500;
            $message = "Failed";
        }
        $response = [
            'available_users' => $available_users,
            'material_list' => $materialRequestList,
            'message' => $message,
        ];
        return response()->json($response,$status);
    }

}