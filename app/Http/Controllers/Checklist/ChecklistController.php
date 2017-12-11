<?php
    /**
     * Created by Harsha.
     * User: harsha
     * Date: 21/11/17
     * Time: 1:42 PM
     */
namespace App\Http\Controllers\Checklist;
use App\ChecklistCategory;
use App\ChecklistStatus;
use App\Permission;
use App\ProjectSite;
use App\ProjectSiteChecklist;
use App\ProjectSiteChecklistCheckpoint;
use App\ProjectSiteChecklistCheckpointImages;
use App\ProjectSiteUserChecklistAssignment;
use App\ProjectSiteUserChecklistHistory;
use App\ProjectSiteUserCheckpoint;
use App\ProjectSiteUserCheckpointImage;
use App\User;
use App\UserHasPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Mockery\Exception;

class ChecklistController extends BaseController
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function getCategoryListing(Request $request){
        try{
            $subCategoryIds = ProjectSiteChecklist::where('project_site_id',$request['project_site_id'])->distinct('checklist_category_id')->pluck('checklist_category_id');
            $categoryIDs = ChecklistCategory::whereIn('id',$subCategoryIds)->pluck('category_id');
            $categories = ChecklistCategory::whereIn('id',$categoryIDs)->get();
            $iterator = 0;
            $categoryList = array();
            foreach($categories as $key => $mainCategory){
                $categoryList[$iterator]['category_id'] = $mainCategory['id'];
                $categoryList[$iterator]['category_name'] = $mainCategory['name'];
                $subCategories = ChecklistCategory::where('category_id',$mainCategory['id'])->get();
                $categoryList[$iterator]['sub_categories'] = array();
                $jIterator = 0;
                foreach($subCategories as $key1 => $subCategory){
                    $categoryList[$iterator]['sub_categories'][$jIterator]['sub_category_id'] = $subCategory['id'];
                    $categoryList[$iterator]['sub_categories'][$jIterator]['sub_category_name'] = $subCategory['name'];
                    $jIterator++;
                }
                $iterator++;
            }
            $data['categories'] = $categoryList;
            $status = 200;
            $message = "Success";

        }catch(\Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Get Category Sub-category Listing',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response($response,$status);
    }

    public function getFloorListing(Request $request){
        try{
            $floorList = ProjectSiteChecklist::join('quotation_floors','quotation_floors.id','=','project_site_checklists.quotation_floor_id')
                                    ->where('project_site_checklists.project_site_id',$request['project_site_id'])->distinct('project_site_checklists.quotation_floor_id')
                                    ->select('project_site_checklists.quotation_floor_id','quotation_floors.name as quotation_floor_name')->get();
            $data['floor_list'] = $floorList;
            $status = 200;
            $message = "Success";
        }catch(\Exception $e){
            $data = [
                'action' => 'Get Floor and title name',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            $status = 500;
            $message = "Fail";
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function getTitleListing(Request $request){
        try{
            $titleList = ProjectSiteChecklist::where('project_site_id',$request['project_site_id'])
                            ->where('quotation_floor_id',$request['quotation_floor_id'])
                            ->select('id as project_site_checklist_id','title','detail')
                            ->get();
            $data['title_list'] = $titleList;
            $status = 200;
            $message = "Success";

        }catch(\Exception $e){
            $data = [
                'action' => 'Get Title Listing',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            $status = 500;
            $message = "Fail";
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function createUserAssignment(Request $request){
        try{
            $user = Auth::user();
            $project_site_user_checklist_assignment['project_site_checklist_id'] = $request['project_site_checklist_id'];
            $project_site_user_checklist_assignment['checklist_status_id'] = ChecklistStatus::where('slug','assigned')->pluck('id')->first();
            $project_site_user_checklist_assignment['assigned_by'] = $user['id'];
            foreach ($request['assigned_to'] as $key => $assignedUserId) {
                $project_site_user_checklist_assignment['assigned_to'] = $assignedUserId;
                ProjectSiteUserChecklistAssignment::create($project_site_user_checklist_assignment);
            }
            $message = "Checklist Assigned Successfully";
            $status = 200;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Create User Assignment',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message'  => $message
        ];
        return response()->json($response,$status);
    }

    public function getChecklistListing(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $data = array();
            $user = Auth::user();
            $checklistStatus = ChecklistStatus::where('slug',$request['checklist_status_slug'])->first();
            $projectSiteChecklists = ProjectSiteChecklist::where('project_site_id',$request['project_site_id'])->pluck('id');
            $userAllChecklistIds = ProjectSiteUserChecklistAssignment::where('checklist_status_id',$checklistStatus['id'])
                ->whereIn('project_site_checklist_id',$projectSiteChecklists)
                ->where(function ($query) use ($user){
                    $query->where('project_site_user_checklist_assignments.assigned_by',$user['id'])
                        ->Orwhere('project_site_user_checklist_assignments.assigned_to',$user['id']);
                })
                ->pluck('project_site_user_checklist_assignments.id');
            $userLatestChecklistIds = array();
            if(count($userAllChecklistIds) > 0){
                $userAllChecklistIds = $userAllChecklistIds->toArray();
                $iterator = 0;
                foreach($userAllChecklistIds as $allChecklistId){
                    if(!in_array($allChecklistId,$userLatestChecklistIds)){
                        $userLatestChecklistIds[$iterator] = $allChecklistId;
                        for($nextChecklistId = ProjectSiteUserChecklistAssignment::whereIn('id',$userAllChecklistIds)->where('project_site_user_checklist_assignment_id',$allChecklistId)->first(); $nextChecklistId != null; $nextChecklistId = ProjectSiteUserChecklistAssignment::where('project_site_user_checklist_assignment_id',$nextChecklistId['id'])->whereIn('id',$userAllChecklistIds)->first()){
                            $userLatestChecklistIds[$iterator] = $nextChecklistId['id'];
                        }
                    }
                    $iterator++;
                }
                $projectSiteUserChecklists = ProjectSiteUserChecklistAssignment::whereIn('id',$userLatestChecklistIds)->get();
            }else{
                $projectSiteUserChecklists = array();
            }
            $iterator = 0;
            $checklistListing = array();
            foreach($projectSiteUserChecklists as $key => $projectSiteUserChecklist) {
                $checklistListing[$iterator]['project_site_user_checklist_assignment_id'] = $projectSiteUserChecklist['id'];
                $projectSiteChecklist = $projectSiteUserChecklist->projectSiteChecklist;
                $checklistListing[$iterator]['project_site_checklist_id'] = $projectSiteChecklist['id'];
                $checklistListing[$iterator]['checklist_current_status'] = $checklistStatus['slug'];
                $checklistListing[$iterator]['assigned_on'] = date('l, d F Y',strtotime($projectSiteUserChecklist['created_at']));
                $subcategoryData = $projectSiteChecklist->checklistCategory;
                $categoryData = ChecklistCategory::where('id',$subcategoryData['category_id'])->first();
                $checklistListing[$iterator]['category_id'] = $categoryData['id'];
                $checklistListing[$iterator]['category_name'] = $categoryData['name'];
                $checklistListing[$iterator]['sub_category_id'] = $subcategoryData['id'];
                $checklistListing[$iterator]['sub_category_name'] = $subcategoryData['name'];
                $checklistListing[$iterator]['floor_name'] = $projectSiteChecklist->quotationFloor->name;
                $checklistListing[$iterator]['title'] = $projectSiteChecklist->title;
                $checklistListing[$iterator]['description'] = $projectSiteChecklist->detail;
                $projectSiteUserCheckpoints = $projectSiteUserChecklist->projectSiteUserCheckpoints;
                $projectSiteUserCheckpointsCompleted = ProjectSiteUserCheckpoint::where('project_site_user_checklist_assignment_id',$projectSiteUserChecklist['id'])->whereNotNull('is_ok')->count();
                $totalCheckpoints = $projectSiteUserCheckpoints->count();
                $checklistListing[$iterator]['total_checkpoints'] = $totalCheckpoints;
                $checklistListing[$iterator]['completed_checkpoints'] = $projectSiteUserCheckpointsCompleted;
                $checklistListing[$iterator]['assigned_to'] = $projectSiteUserChecklist['assigned_to'];
                $assignedToUser = $projectSiteUserChecklist->assignedToUser;
                $checklistListing[$iterator]['assigned_to_user_name'] = $assignedToUser['first_name'].' '.$assignedToUser['last_name'];
                $checklistListing[$iterator]['assigned_by'] = $projectSiteUserChecklist['assigned_by'];
                $assignedByUser = $projectSiteUserChecklist->assignedByUser;
                $checklistListing[$iterator]['assigned_by_user_name'] = $assignedByUser['first_name'].' '.$assignedByUser['last_name'];
                $checklistListing[$iterator]['reviewed_by'] = $projectSiteUserChecklist['reviewed_by'];
                $reviewedByUser = $projectSiteUserChecklist->reviewedByUser;
                $checklistListing[$iterator]['reviewed_by_user_name'] = $reviewedByUser['first_name'].' '.$reviewedByUser['last_name'];
                $iterator++;
            }
            $data['checklist_data'] = $checklistListing;

        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Checklist Listing',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function getCheckPointListing(Request $request){
        try{
            $message = "Success";
            $status = 200;
            if($request->has('project_site_user_checklist_assignment_id')){
                $projectSiteUserChecklist = ProjectSiteUserChecklistAssignment::where('id',$request['project_site_user_checklist_assignment_id'])->first();
                $projectSiteUserCheckpoints = ProjectSiteUserCheckpoint::where('project_site_user_checklist_assignment_id',$request['project_site_user_checklist_assignment_id'])->orderBy('id','asc')
                    ->get();
                $iterator = 0;
                $checkPointListing = array();
                foreach($projectSiteUserCheckpoints as $key => $projectSiteUserCheckpoint){
                    $checkPointListing[$iterator]['project_site_user_checkpoint_id'] = $projectSiteUserCheckpoint['id'];
                    $projectSiteChecklistCheckpoint = $projectSiteUserCheckpoint->projectSiteChecklistCheckpoint;
                    $checkPointListing[$iterator]['project_site_user_checkpoint_description'] = $projectSiteChecklistCheckpoint->description;
                    $checkPointListing[$iterator]['project_site_user_checkpoint_is_remark_required'] = $projectSiteChecklistCheckpoint->is_remark_required;
                    $checkPointListing[$iterator]['project_site_user_checkpoint_is_checked'] = ($projectSiteUserCheckpoint['is_ok'] !== null) ? true : false;
                    $checkPointListing[$iterator]['project_site_user_checkpoint_is_ok'] = $projectSiteUserCheckpoint['is_ok'];
                    $checkPointListing[$iterator]['project_site_user_checkpoint_images'] = array();
                    $projectSiteChecklistCheckpointImages = $projectSiteChecklistCheckpoint->projectSiteChecklistCheckpointImages;
                    $jIterator = 0;
                    foreach($projectSiteChecklistCheckpointImages as $key1 => $projectSiteChecklistCheckpointImage){
                        $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_id'] = $projectSiteChecklistCheckpointImage['id'];
                        $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_caption'] = $projectSiteChecklistCheckpointImage['caption'];
                        $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_is_required'] = $projectSiteChecklistCheckpointImage['is_required'];
                        $imageUrl = ProjectSiteUserCheckpointImage::where('project_site_user_checkpoint_id',$projectSiteUserCheckpoint['id'])->where('project_site_checklist_checkpoint_image_id',$projectSiteChecklistCheckpointImage['id'])->first();
                        if(count($imageUrl) > 0){
                            $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_user_checkpoint_image_id'] = $imageUrl['id'];
                            $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_user_checkpoint_image_url'] = $imageUrl['image'];
                        }else{
                            $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_user_checkpoint_image_id'] = null;
                            $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_user_checkpoint_image_url'] = null;
                        }
                        $jIterator++;
                    }
                    $iterator++;
                    $checkListData = array();
                    if($projectSiteUserChecklist['project_site_user_checklist_assignment_id'] != null){
                        $parentCount = 0;
                        $nextParentId = $projectSiteUserChecklist['project_site_user_checklist_assignment_id'];
                        do{
                            $checkListData[$parentCount]['project_site_user_checklist_assignment_id'] = $nextParentId;
                            $nextParentId = ProjectSiteUserChecklistAssignment::where('id',$nextParentId)->pluck('project_site_user_checklist_assignment_id');
                            $parentCount ++;
                        }while($nextParentId == null);
                    }
                    $data['parent_checklist'] = $checkListData;
                }
            }else{
                $projectSiteChecklistCheckpoints = ProjectSiteChecklistCheckpoint::where('project_site_checklist_id',$request['project_site_checklist_id'])->orderBy('id','asc')
                    ->get();
                $iterator = 0;
                $checkPointListing = $checkListData = array();
                foreach($projectSiteChecklistCheckpoints as $key => $projectSiteChecklistCheckpoint){
                    $checkPointListing[$iterator]['project_site_checklist_checkpoint_id'] = $projectSiteChecklistCheckpoint['id'];
                    $checkPointListing[$iterator]['project_site_checklist_checkpoint_description'] = $projectSiteChecklistCheckpoint['description'];
                    $checkPointListing[$iterator]['project_site_checklist_is_remark_required'] = $projectSiteChecklistCheckpoint['is_remark_required'];
                    $projectSiteChecklistCheckpointImages = $projectSiteChecklistCheckpoint->projectSiteChecklistCheckpointImages;
                    $jIterator = 0;
                    if(count($projectSiteChecklistCheckpointImages) > 0){
                        foreach($projectSiteChecklistCheckpointImages as $key1 => $projectSiteChecklistCheckpointImage){
                            $checkPointListing[$iterator]['project_site_checklist_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_id'] = $projectSiteChecklistCheckpointImage['id'];
                            $checkPointListing[$iterator]['project_site_checklist_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_caption'] = $projectSiteChecklistCheckpointImage['caption'];
                            $checkPointListing[$iterator]['project_site_checklist_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_is_required'] = $projectSiteChecklistCheckpointImage['is_required'];
                            $jIterator++;
                        }
                    }else{
                        $checkPointListing[$iterator]['project_site_checklist_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_id'] = null;
                        $checkPointListing[$iterator]['project_site_checklist_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_caption'] = null;
                        $checkPointListing[$iterator]['project_site_checklist_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_is_required'] = null;
                    }
                    $iterator++;
                }
            }

            $data['check_points'] = $checkPointListing;

        }catch(\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Get Checkpoint Listing',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function getUserWithAssignAcl(Request $request){
        try{
            $checklistManagementACl = Permission::whereIn('name',['create-checklist-management','view-checklist-management'])->pluck('id');
            $userIDs = UserHasPermission::join('user_project_site_relation','user_project_site_relation.user_id','=','user_has_permissions.user_id')
                ->whereIn('user_has_permissions.permission_id',$checklistManagementACl)
                ->where('user_project_site_relation.project_site_id',$request['project_site_id'])
                ->distinct('user_has_permissions.user_id')
                ->pluck('user_has_permissions.user_id');

            $users = User::whereIn('id',$userIDs)->select('id as user_id','first_name','last_name')->get()->toArray();
            $data['users'] = $users;
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get User With Assign Acl',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function saveCheckpointDetails(Request $request){
        try{
            $message = "Details saved successfully";
            $status = 200;
            $user = Auth::user();
            $updateProjectSiteUserCheckpoint['is_ok'] = $request['is_ok'];
            $updateProjectSiteUserCheckpoint['remark'] = $request['remark'];
            ProjectSiteUserCheckpoint::where('id',$request['project_site_user_checkpoint_id'])->update($updateProjectSiteUserCheckpoint);
            $projectSiteUserCheckpoint = ProjectSiteUserCheckpoint::where('id',$request['project_site_user_checkpoint_id'])->first();
            $sha1UserId = sha1($user['id']);
            foreach ($request['images'] as $key => $imageData){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('CHECKLIST_CHECKPOINT_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageData['image'];
                    if(File::exists($tempUploadFile)){
                        $sha1ProjectSiteUserCheckpointId = sha1($projectSiteUserCheckpoint['id']);
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('CHECKLIST_CHECKPOINT_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.'checkpoint'.DIRECTORY_SEPARATOR.$sha1ProjectSiteUserCheckpointId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageData['image'];
                        File::move($tempUploadFile,$imageUploadNewPath);
                        ProjectSiteUserCheckpointImage::create([
                            'name' => $imageData['image'] ,
                            'project_site_user_checkpoint_id' => $projectSiteUserCheckpoint['id'],
                            'project_site_checklist_checkpoint_image_id' => $imageData['project_site_checklist_checkpoint_image_id'],
                            'image' => $imageData['image']
                        ]);
                    }
                }
        }catch(\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Save Checkpoint Details',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function changeChecklistStatus(Request $request){
        try{
            $message = "Status changed successfully";
            $status = 200;
            $updateProjectSiteChecklistAssignment['checklist_status_id'] = ChecklistStatus::where('slug',$request['checklist_status_slug'])->pluck('id')->first();
            ProjectSiteUserChecklistAssignment::where('id',$request['project_site_user_checklist_assignment_id'])->update($updateProjectSiteChecklistAssignment);
            $projectSiteUserChecklistHistory['checklist_status_id'] = $updateProjectSiteChecklistAssignment['checklist_status_id'];
            $projectSiteUserChecklistHistory['project_site_user_checklist_assignment_id'] = $request['project_site_user_checklist_assignment_id'];
            $projectSiteUserChecklistHistory['checklist_status_id'] = $updateProjectSiteChecklistAssignment['checklist_status_id'];
            if($request->has('remark')){
                $projectSiteUserChecklistHistory['remark'] = $request['remark'];
            }
            ProjectSiteUserChecklistHistory::create($projectSiteUserChecklistHistory);
        }catch(\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Change Checklist Status',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function recheckCheckpoints(Request $request){
        try{
            $user = Auth::user();
            $checklistStatusIDs = ChecklistStatus::whereIn('slug',['assigned','recheck'])->get();
            $parentProjectSiteUserChecklistAssignment = ProjectSiteUserChecklistAssignment::where('id',$request['project_site_user_checklist_assignment_id'])->first();
            $projectSiteUserChecklistAssignment['project_site_checklist_id'] = $parentProjectSiteUserChecklistAssignment['project_site_checklist_id'];
            $projectSiteUserChecklistAssignment['checklist_status_id'] = $checklistStatusIDs->where('slug','assigned')->pluck('id')->first();
            $projectSiteUserChecklistAssignment['assigned_to'] = $request['user_id'];
            $projectSiteUserChecklistAssignment['assigned_by'] = $user['id'];
            $projectSiteUserChecklistAssignment['project_site_user_checklist_assignment_id'] = $parentProjectSiteUserChecklistAssignment['id'];
            $projectSiteUserChecklistAssignmentData = ProjectSiteUserChecklistAssignment::create($projectSiteUserChecklistAssignment);
            ProjectSiteUserChecklistHistory::create([
                'checklist_status_id' => $projectSiteUserChecklistAssignment['checklist_status_id'],
                'project_site_user_checklist_assignment_id' => $projectSiteUserChecklistAssignmentData['id'],
            ]);
            foreach($request['project_site_checklist_checkpoint_id'] as $key => $projectSiteChecklistCheckpointId){
                $projectSiteUserCheckpoint['project_site_checklist_checkpoint_id'] = $projectSiteChecklistCheckpointId;
                //$projectSiteUserCheckpoint['project_site_user_checkpoint_id'] = $projectSiteChecklistCheckpointId;
                $projectSiteUserCheckpoint['project_site_user_checklist_assignment_id'] = $projectSiteUserChecklistAssignmentData['id'];
                ProjectSiteUserCheckpoint::create($projectSiteUserCheckpoint);
            }
            $reviewChecklistStatusId = $checklistStatusIDs->where('slug','recheck')->pluck('id')->first();
            $parentProjectSiteUserChecklistAssignment->update([
                'checklist_status_id' => $reviewChecklistStatusId,
                'reviewed_by' => $user['id']
            ]);
            ProjectSiteUserChecklistHistory::create([
                'checklist_status_id' => $reviewChecklistStatusId,
                'project_site_user_checklist_assignment_id' => $parentProjectSiteUserChecklistAssignment['id'],
                'remark' => $request['remark'],
            ]);
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Recheck Checkpoints',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function getParentChecklist(Request $request){
        try{
            $projectSiteUserChecklistAssignmentId = ProjectSiteUserChecklistAssignment::where('id',$request['project_site_user_checklist_assignment_id'])->pluck('project_site_user_checklist_assignment_id');
            $projectSiteUserChecklist = ProjectSiteUserChecklistAssignment::where('id',$projectSiteUserChecklistAssignmentId)->first();
            $parentChecklist['project_site_user_checklist_assignment_id'] = $projectSiteUserChecklist['id'];
            $projectSiteChecklist = $projectSiteUserChecklist->projectSiteChecklist;
            $parentChecklist['project_site_checklist_id'] = $projectSiteChecklist['id'];
            $parentChecklist['checklist_current_status'] = $projectSiteUserChecklist->checklistStatus->name;
            $parentChecklist['assigned_on'] = date('l, d F Y',strtotime($projectSiteUserChecklist['created_at']));
            $subcategoryData = $projectSiteChecklist->checklistCategory;
            $categoryData = ChecklistCategory::where('id',$subcategoryData['category_id'])->first();
            $parentChecklist['category_id'] = $categoryData['id'];
            $parentChecklist['category_name'] = $categoryData['name'];
            $parentChecklist['sub_category_id'] = $subcategoryData['id'];
            $parentChecklist['sub_category_name'] = $subcategoryData['name'];
            $parentChecklist['floor_name'] = $projectSiteChecklist->quotationFloor->name;
            $parentChecklist['title'] = $projectSiteChecklist->title;
            $parentChecklist['description'] = $projectSiteChecklist->detail;
            $projectSiteUserCheckpoints = $projectSiteUserChecklist->projectSiteUserCheckpoints;
            $projectSiteUserCheckpointsCompleted = ProjectSiteUserCheckpoint::where('project_site_user_checklist_assignment_id',$projectSiteUserChecklist['id'])->whereNotNull('is_ok')->count();
            $totalCheckpoints = $projectSiteUserCheckpoints->count();
            $parentChecklist['total_checkpoints'] = $totalCheckpoints;
            $parentChecklist['completed_checkpoints'] = $projectSiteUserCheckpointsCompleted;
            $parentChecklist['assigned_to'] = $projectSiteUserChecklist['assigned_to'];
            $assignedToUser = $projectSiteUserChecklist->assignedToUser;
            $parentChecklist['assigned_to_user_name'] = $assignedToUser['first_name'].' '.$assignedToUser['last_name'];
            $parentChecklist['assigned_by'] = $projectSiteUserChecklist['assigned_by'];
            $assignedByUser = $projectSiteUserChecklist->assignedByUser;
            $parentChecklist['assigned_by_user_name'] = $assignedByUser['first_name'].' '.$assignedByUser['last_name'];
            $parentChecklist['reviewed_by'] = $projectSiteUserChecklist['reviewed_by'];
            $reviewedByUser = $projectSiteUserChecklist->reviewedByUser;
            $parentChecklist['reviewed_by_user_name'] = $reviewedByUser['first_name'].' '.$reviewedByUser['last_name'];
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $message = 'Fail';
            $status = 500;
            $data = [
                'action' => 'Parent Checklist',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
            'data' => $parentChecklist
        ];
        return response()->json($response,$status);
    }
}