<?php

namespace App\Http\Controllers\User;
use App\Module;
use App\Permission;
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
            $status = 200;
            $message = "Success";
            $purchaseRequestModule = Module::where('slug','purchase-request')->pluck('id')->first();
            $approvalAclPermission = Permission::where('module_id',$purchaseRequestModule)->where('name','approve-purchase-request')->first();
            $userIds = UserHasPermission::where('permission_id',$approvalAclPermission->id)->pluck('user_id');
            $users = User::whereIn('id',$userIds)->get();
            $iterator = 0;
            $available_users = array();
            foreach ($users as $key => $user){
                $available_users[$iterator]['id'] = $user->id;
                $available_users[$iterator]['user_name'] = $user->first_name." ".$user->last_name;
                $iterator++;
            }
        }catch(\Exception $e){
            $available_users = array();
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
            'message' => $message
        ];
        return response()->json($response,$status);
    }

}