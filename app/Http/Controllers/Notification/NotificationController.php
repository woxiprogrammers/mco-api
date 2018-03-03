<?php
/**
 * Created by Ameya Joshi.
 * Date: 27/12/17
 * Time: 1:25 PM
 */

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\CustomTraits\NotificationTrait;
use App\InventoryComponentTransfers;
use App\InventoryComponentTransferStatus;
use App\InventoryTransferTypes;
use App\MaterialRequests;
use App\PeticashRequestedSalaryTransaction;
use App\ProjectSiteUserChecklistAssignment;
use App\PurchaseOrder;
use App\PurchaseOrderRequest;
use App\PurchaseOrderTransaction;
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
            $response['data'] = $this->getSiteWiseNotificationCount($user,$request->project_site_id);
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
                    $projects[$kIterator]['notification_count'] = array_sum($this->getSiteWiseNotificationCount($user,$projectSite->id));
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

    public function getSiteWiseNotificationCount($user, $projectSiteId){
        try{
            $materialRequestCreateCount = $materialRequestDisapprovedCount = 0;
            $purchaseRequestCreateCount = $purchaseRequestDisapprovedCount = 0;
            $purchaseOrderCreatedCount = $purchaseOrderBillCreateCount = 0;
            $purchaseOrderRequestCreateCount = $materialSiteOutTransferCreateCount = 0;
            $materialSiteOutTransferApproveCount = $checklistAssignedCount = 0;
            $checklistAssignedCount = $reviewChecklistCount = 0;
            $peticashSalaryRequestCount = $peticashSalaryApprovedCount = 0;
            if(!in_array($user->roles[0]->role->slug, ['admin','superadmin'])){
                if($user->customHasPermission('approve-material-request')){
                    $materialRequestCreateCount = MaterialRequests::join('material_request_components','material_requests.id','=','material_request_components.material_request_id')
                        ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_components.component_status_id')
                        ->join('user_project_site_relation','user_project_site_relation.project_site_id','=','material_requests.project_site_id')
                        ->where('user_project_site_relation.user_id',$user->id)
                        ->where('purchase_request_component_statuses.slug','pending')
                        ->where('material_requests.project_site_id', $projectSiteId)
                        ->count();
                }
                if($user->customHasPermission('approve-purchase-request')){
                    $purchaseRequestCreateCount = PurchaseRequests::join('purchase_request_components','purchase_request_components.purchase_request_id','=','purchase_requests.id')
                        ->join('material_request_components','purchase_request_components.material_request_component_id','=','material_request_components.id')
                        ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_components.component_status_id')
                        ->join('user_project_site_relation','user_project_site_relation.project_site_id','=','purchase_requests.project_site_id')
                        ->where('user_project_site_relation.user_id',$user->id)
                        ->where('purchase_request_component_statuses.slug','p-r-assigned')
                        ->where('purchase_requests.project_site_id', $projectSiteId)
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
                            ->where('material_requests.project_site_id', $projectSiteId)
                            ->count('material_request_components.id');
                    }else{
                        $materialRequestDisapprovedCount = MaterialRequests::join('material_request_components','material_requests.id','=','material_request_components.material_request_id')
                            ->join('material_request_component_history_table','material_request_component_history_table.material_request_component_id','=','material_request_components.id')
                            ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_components.component_status_id')
                            ->whereIn('purchase_request_component_statuses.slug',['manager-disapproved','admin-disapproved'])
                            ->where('material_requests.on_behalf_of',$user->id)
                            ->where('material_requests.project_site_id', $projectSiteId)
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
                            ->where('material_requests.project_site_id', $projectSiteId)
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
                            ->where('material_requests.project_site_id', $projectSiteId)
                            ->count();
                    }
                    $lastLoginForPO = UserLastLogin::join('modules','modules.id','=','user_last_logins.module_id')
                        ->whereIn('modules.slug',['material-request','purchase-request'])
                        ->where('user_last_logins.user_id',$user->id)
                        ->orderBy('user_last_logins.updated_at','desc')
                        ->pluck('user_last_logins.last_login')
                        ->first();
                    if($lastLoginForPO == null){
                        $purchaseOrderCreatedCount = PurchaseOrder::join('purchase_requests','purchase_requests.id','=','purchase_orders.purchase_request_id')
                            ->join('purchase_request_components','purchase_request_components.purchase_request_id','=','purchase_requests.id')
                            ->join('material_request_components','material_request_components.id','=','purchase_request_components.material_request_component_id')
                            ->join('material_requests','material_requests.id','=','material_request_components.material_request_id')
                            ->where(function($query) use ($user){
                                $query->where('material_requests.on_behalf_of', $user->id)
                                    ->orWhere('purchase_requests.behalf_of_user_id', $user->id);
                            })
                            ->where('material_requests.project_site_id', $projectSiteId)
                            ->count('purchase_orders.id');
                        $purchaseOrderBillCreateCount = PurchaseOrderTransaction::join('purchase_orders','purchase_orders.id','=','purchase_order_transactions.purchase_order_id')
                            ->join('purchase_requests','purchase_requests.id','=','purchase_orders.purchase_request_id')
                            ->join('purchase_request_components','purchase_request_components.purchase_request_id','=','purchase_requests.id')
                            ->join('material_request_components','material_request_components.id','=','purchase_request_components.material_request_component_id')
                            ->join('material_requests','material_requests.id','=','material_request_components.material_request_id')
                            ->join('material_request_component_history_table','material_request_component_history_table.material_request_component_id','=','material_request_components.id')
                            ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_component_history_table.component_status_id')
                            ->where(function($query) use ($user){
                                $query->where('material_requests.on_behalf_of', $user->id)
                                    ->orWhere(function($innerQuery) use ($user){
                                        $innerQuery->whereIn('purchase_request_component_statuses.slug',['p-r-manager-approved','p-r-admin-approved'])
                                            ->where('material_request_component_history_table.user_id', $user->id);
                                    });
                            })
                            ->where('material_requests.project_site_id', $projectSiteId)
                            ->count('purchase_order_transactions.id');
                    }else{
                        $purchaseOrderCreatedCount = PurchaseOrder::join('purchase_requests','purchase_requests.id','=','purchase_orders.purchase_request_id')
                            ->join('purchase_request_components','purchase_request_components.purchase_request_id','=','purchase_requests.id')
                            ->join('material_request_components','material_request_components.id','=','purchase_request_components.material_request_component_id')
                            ->join('material_requests','material_requests.id','=','material_request_components.material_request_id')
                            ->where(function($query) use ($user){
                                $query->where('material_requests.on_behalf_of', $user->id)
                                    ->orWhere('purchase_requests.behalf_of_user_id', $user->id);
                            })
                            ->where('material_requests.project_site_id', $projectSiteId)
                            ->where('purchase_orders.created_at','>=',$lastLoginForPO)
                            ->count('purchase_orders.id');

                        $purchaseOrderBillCreateCount = PurchaseOrderTransaction::join('purchase_orders','purchase_orders.id','=','purchase_order_transactions.purchase_order_id')
                            ->join('purchase_requests','purchase_requests.id','=','purchase_orders.purchase_request_id')
                            ->join('purchase_request_components','purchase_request_components.purchase_request_id','=','purchase_requests.id')
                            ->join('material_request_components','material_request_components.id','=','purchase_request_components.material_request_component_id')
                            ->join('material_requests','material_requests.id','=','material_request_components.material_request_id')
                            ->join('material_request_component_history_table','material_request_component_history_table.material_request_component_id','=','material_request_components.id')
                            ->join('purchase_request_component_statuses','purchase_request_component_statuses.id','=','material_request_component_history_table.component_status_id')
                            ->where(function($query) use ($user){
                                $query->where('material_requests.on_behalf_of', $user->id)
                                    ->orWhere(function($innerQuery) use ($user){
                                        $innerQuery->whereIn('purchase_request_component_statuses.slug',['p-r-manager-approved','p-r-admin-approved'])
                                            ->where('material_request_component_history_table.user_id', $user->id);
                                    });
                            })
                            ->where('material_requests.project_site_id', $projectSiteId)
                            ->where('purchase_order_transactions.created_at', '>=',$lastLoginForPO)
                            ->count('purchase_order_transactions.id');
                    }
                }

                if($user->customHasPermission('approve-purchase-order-request')){
                    $lastLogin = UserLastLogin::join('modules','modules.id','=','user_last_logins.module_id')
                        ->where('modules.slug','purchase-order-request')
                        ->where('user_last_logins.user_id',$user->id)
                        ->pluck('user_last_logins.last_login')
                        ->first();
                    if($lastLogin == null){
                        $purchaseOrderRequestCreateCount = PurchaseOrderRequest::join('purchase_requests','purchase_requests.id','=','purchase_order_requests.purchase_request_id')
                            ->join('user_project_site_relation','user_project_site_relation.project_site_id','=','purchase_requests.project_site_id')
                            ->join('user_has_permissions','user_has_permissions.user_id','=','user_project_site_relation.user_id')
                            ->join('permissions','permissions.id','=','user_has_permissions.permission_id')
                            ->where('permissions.name','approve-purchase-order-request')
                            ->where('user_project_site_relation.user_id', $user->id)
                            ->where('purchase_requests.project_site_id', $projectSiteId)
                            ->count('purchase_order_requests.id');
                    }else{
                        $purchaseOrderRequestCreateCount = PurchaseOrderRequest::join('purchase_requests','purchase_requests.id','=','purchase_order_requests.purchase_request_id')
                            ->join('user_project_site_relation','user_project_site_relation.project_site_id','=','purchase_requests.project_site_id')
                            ->join('user_has_permissions','user_has_permissions.user_id','=','user_project_site_relation.user_id')
                            ->join('permissions','permissions.id','=','user_has_permissions.permission_id')
                            ->where('permissions.name','approve-purchase-order-request')
                            ->where('user_project_site_relation.user_id', $user->id)
                            ->where('purchase_requests.project_site_id', $projectSiteId)
                            ->where('purchase_order_requests.created_at','>=', $lastLogin)
                            ->count('purchase_order_requests.id');
                    }
                }
                if($user->customHasPermission('approve-component-transfer')){
                    $lastLogin = UserLastLogin::join('modules','modules.id','=','user_last_logins.module_id')
                        ->where('modules.slug','component-transfer')
                        ->where('user_last_logins.user_id',$user->id)
                        ->pluck('user_last_logins.last_login')
                        ->first();
                    $siteOutTransferTypeId = InventoryTransferTypes::where('slug','site')->where('type','ilike','out')->pluck('id')->first();
                    $inventoryRequestedStatusId = InventoryComponentTransferStatus::where('slug','requested')->pluck('id')->first();
                    if($lastLogin == null){
                        $materialSiteOutTransferCreateCount = InventoryComponentTransfers::join('inventory_components','inventory_components.id','=','inventory_component_transfers.inventory_component_id')
                            ->join('user_project_site_relation','user_project_site_relation.project_site_id','=','inventory_components.project_site_id')
                            ->where('user_project_site_relation.user_id', $user->id)
                            ->where('inventory_components.project_site_id',$projectSiteId)
                            ->where('inventory_component_transfers.transfer_type_id', $siteOutTransferTypeId)
                            ->where('inventory_component_transfers.inventory_component_transfer_status_id', $inventoryRequestedStatusId)
                            ->count('inventory_component_transfers.id');
                    }else{
                        $materialSiteOutTransferCreateCount = InventoryComponentTransfers::join('inventory_components','inventory_components.id','=','inventory_component_transfers.inventory_component_id')
                            ->join('user_project_site_relation','user_project_site_relation.project_site_id','=','inventory_components.project_site_id')
                            ->where('user_project_site_relation.user_id', $user->id)
                            ->where('inventory_components.project_site_id',$projectSiteId)
                            ->where('inventory_component_transfers.transfer_type_id', $siteOutTransferTypeId)
                            ->where('inventory_component_transfers.inventory_component_transfer_status_id', $inventoryRequestedStatusId)
                            ->where('inventory_component_transfers.created_at','>=', $lastLogin)
                            ->count('inventory_component_transfers.id');
                    }
                }
                if($user->customHasPermission('approve-component-transfer') || $user->customHasPermission('approve-component-transfer')){
                    $lastLogin = UserLastLogin::join('modules','modules.id','=','user_last_logins.module_id')
                        ->where('modules.slug','component-transfer')
                        ->where('user_last_logins.user_id',$user->id)
                        ->pluck('user_last_logins.last_login')
                        ->first();
                    $siteOutTransferTypeId = InventoryTransferTypes::where('slug','site')->where('type','ilike','out')->pluck('id')->first();
                    $inventoryApprovedStatusId = InventoryComponentTransferStatus::where('slug','approved')->pluck('id')->first();
                    if($lastLogin == null){
                        $materialSiteOutTransferApproveCount = InventoryComponentTransfers::join('inventory_components','inventory_components.id','=','inventory_component_transfers.inventory_component_id')
                            ->join('user_project_site_relation','user_project_site_relation.project_site_id','=','inventory_components.project_site_id')
                            ->where('user_project_site_relation.user_id', $user->id)
                            ->where('inventory_components.project_site_id',$projectSiteId)
                            ->where('inventory_component_transfers.transfer_type_id', $siteOutTransferTypeId)
                            ->where('inventory_component_transfers.inventory_component_transfer_status_id', $inventoryApprovedStatusId)
                            ->where('inventory_component_transfers.user_id', $user->id)
                            ->count('inventory_component_transfers.id');
                    }else{
                        $materialSiteOutTransferApproveCount = InventoryComponentTransfers::join('inventory_components','inventory_components.id','=','inventory_component_transfers.inventory_component_id')
                            ->join('user_project_site_relation','user_project_site_relation.project_site_id','=','inventory_components.project_site_id')
                            ->where('user_project_site_relation.user_id', $user->id)
                            ->where('inventory_components.project_site_id',$projectSiteId)
                            ->where('inventory_component_transfers.transfer_type_id', $siteOutTransferTypeId)
                            ->where('inventory_component_transfers.inventory_component_transfer_status_id', $inventoryApprovedStatusId)
                            ->where('inventory_component_transfers.updated_at','>=', $lastLogin)
                            ->where('inventory_component_transfers.user_id', $user->id)
                            ->count('inventory_component_transfers.id');
                    }
                }
                if($user->customHasPermission('create-checklist-management')){
                    $checklistAssignedCount = ProjectSiteUserChecklistAssignment::join('checklist_statuses','checklist_statuses.id','=','project_site_user_checklist_assignments.checklist_status_id')
                        ->join('project_site_checklists','project_site_checklists.id','=','project_site_user_checklist_assignments.project_site_checklist_id')
                        ->whereIn('checklist_statuses.slug',['assigned','in-progress'])
                        ->where('project_site_user_checklist_assignments.assigned_to',$user->id)
                        ->where('project_site_checklists.project_site_id', $projectSiteId)
                        ->count('project_site_user_checklist_assignments.id');
                }
                if($user->customHasPermission('create-checklist-recheck') || $user->customHasPermission('view-checklist-recheck')){
                    $reviewChecklistCount = ProjectSiteUserChecklistAssignment::join('project_site_checklists','project_site_user_checklist_assignments.project_site_checklist_id','=','project_site_checklists.id')
                        ->join('checklist_statuses','checklist_statuses.id','=','project_site_user_checklist_assignments.checklist_status_id')
                        ->where('checklist_statuses.slug','review')
                        ->where('project_site_checklists.project_site_id',$projectSiteId)
                        ->count('project_site_user_checklist_assignments.id');
                }
                if($user->customHasPermission('approve-peticash-management')){
                    $peticashSalaryRequestCount = PeticashRequestedSalaryTransaction::join('peticash_statuses','peticash_statuses.id','=','peticash_requested_salary_transactions.peticash_status_id')
                        ->where('peticash_statuses.slug','pending')
                        ->where('peticash_requested_salary_transactions.project_site_id', $projectSiteId)
                        ->count('peticash_requested_salary_transactions.id');
                }
                if($user->customHasPermission('approve-peticash-management') || $user->customHasPermission('create-peticash-management')){
                    $lastLogin = UserLastLogin::join('modules','modules.id','=','user_last_logins.module_id')
                        ->where('modules.slug','peticash-management')
                        ->where('user_last_logins.user_id',$user->id)
                        ->pluck('user_last_logins.last_login')
                        ->first();
                    if($lastLogin == null){
                        $peticashSalaryApprovedCount = PeticashRequestedSalaryTransaction::join('peticash_statuses','peticash_statuses.id','=','peticash_requested_salary_transactions.peticash_status_id')
                            ->where('peticash_statuses.slug','approved')
                            ->where('peticash_requested_salary_transactions.project_site_id',$projectSiteId)
                            ->where('peticash_requested_salary_transactions.reference_user_id', $user->id)
                            ->count();
                    }else{
                        $peticashSalaryApprovedCount = PeticashRequestedSalaryTransaction::join('peticash_statuses','peticash_statuses.id','=','peticash_requested_salary_transactions.peticash_status_id')
                            ->where('peticash_statuses.slug','approved')
                            ->where('peticash_requested_salary_transactions.project_site_id',$projectSiteId)
                            ->where('peticash_requested_salary_transactions.reference_user_id', $user->id)
                            ->where('peticash_requested_salary_transactions.updated_at','>=', $lastLogin)
                            ->count();
                    }
                }
            }
            $notificationCountArray = [
                'material_request_create_count' => $materialRequestCreateCount,
                'material_request_disapproved_count' => $materialRequestDisapprovedCount,
                'purchase_request_create_count' => $purchaseRequestCreateCount,
                'purchase_request_disapproved_count' => $purchaseRequestDisapprovedCount,
                'purchase_order_create_count' => $purchaseOrderCreatedCount,
                'purchase_order_bill_create_count' => $purchaseOrderBillCreateCount,
                'purchase_order_request_create_count' => $purchaseOrderRequestCreateCount,
                'material_site_out_transfer_create_count' => $materialSiteOutTransferCreateCount,
                'material_site_out_transfer_approve_count' => $materialSiteOutTransferApproveCount,
                'checklist_assigned_count' => $checklistAssignedCount,
                'review_checklist_count' => $reviewChecklistCount,
                'salary_request_count' => $peticashSalaryRequestCount,
                'salary_approved_count' => $peticashSalaryApprovedCount
            ];
        }catch(\Exception $e){
            $data = [
                'action' => 'Get Sitewise Notification count',
                'user' => $user,
                'project_site' => ProjectSite::findOrFail($projectSiteId)->toArray(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $notificationCountArray = [
                'material_request_create_count' => 0,
                'material_request_disapproved_count' => 0,
                'purchase_request_create_count' => 0,
                'purchase_request_disapproved_count' => 0,
                'purchase_order_create_count' => 0,
                'purchase_order_bill_create_count' => 0,
                'purchase_order_request_create_count' => 0,
                'material_site_out_transfer_create_count' => 0,
                'material_site_out_transfer_approve_count' => 0,
                'checklist_assigned_count' => 0,
                'review_checklist_count' => 0,
                'salary_request_count' => 0,
                'salary_approved_count' => 0
            ];
        }
        return $notificationCountArray;
    }
}
