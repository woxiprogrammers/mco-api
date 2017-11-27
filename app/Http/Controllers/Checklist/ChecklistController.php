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
use App\ProjectSiteChecklist;
use App\ProjectSiteChecklistCheckpoint;
use App\ProjectSiteUserChecklistAssignment;
use App\ProjectSiteUserCheckpoint;
use App\ProjectSiteUserCheckpointImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

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

    /*public function getDescriptionListing(Request $request){
        try{
            $user = Auth::user();
            $message = "Success";
            $status = 200;
            $projectSiteChecklistCheckpoints = ProjectSiteUserCheckpoint::where('')
            $projectSiteChecklistCheckpoints = ProjectSiteChecklistCheckpoint::where('project_site_checklist_id',$request['project_site_checklist_id'])
                                                ->select('id as project_site_checklist_id','description')->get();
            $data['project_site_checklist_checkpoints'] = $projectSiteChecklistCheckpoints;
        }catch(\Exception $e){
            $message = "Fai;";
            $status = 500;
            $data = [
                'action' => 'Get Description',
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
    }*/

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
            switch ($request['checklist_status_slug']){
                case 'assigned' :
                    $projectSiteUserChecklists = ProjectSiteUserChecklistAssignment::join('project_site_checklists','project_site_checklists.id','=','project_site_user_checklist_assignments.project_site_checklist_id')
                        ->where('project_site_user_checklist_assignments.checklist_status_id',ChecklistStatus::where('slug',$request['checklist_status_slug'])->pluck('id')->first())
                        ->where('project_site_checklists.project_site_id',$request['project_site_id'])
                        ->where(function ($query) use ($user){
                            $query->where('project_site_user_checklist_assignments.assigned_by',$user['id'])
                                ->Orwhere('project_site_user_checklist_assignments.assigned_to',$user['id']);
                        })->get();
                    break;
            }
            $iterator = 0;
            $checklistListing = array();
            foreach($projectSiteUserChecklists as $key => $projectSiteUserChecklist) {
                $checklistListing[$iterator]['project_site_user_checklist_assignment_id'] = $projectSiteUserChecklist['id'];
                $projectSiteChecklist = $projectSiteUserChecklist->projectSiteChecklist;
                $checklistListing[$iterator]['project_site_checklist_id'] = $projectSiteChecklist['id'];
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
                $checklistListing[$iterator]['total_checkpoints'] = 12;
                if($projectSiteUserChecklist['assigned_by'] == $user['id']){
                    $checklistListing[$iterator]['assigned_user'] = $projectSiteUserChecklist['assigned_to'];
                    $assignedToUser = $projectSiteUserChecklist->assignedToUser;
                    $checklistListing[$iterator]['assigned_user_name'] = $assignedToUser['first_name'].' '.$assignedToUser['last_name'];
                }elseif($projectSiteUserChecklist['assigned_to'] == $user['id']){
                    $checklistListing[$iterator]['assigned_user'] = $projectSiteUserChecklist['assigned_by'];
                    $assignedByUser = $projectSiteUserChecklist->assignedByUser;
                    $checklistListing[$iterator]['assigned_user_name'] = $assignedByUser['first_name'].' '.$assignedByUser['last_name'];
                }else{
                    $checklistListing[$iterator]['assigned_user'] = $projectSiteUserChecklist['assigned_by'];
                    $assignedByUser = $projectSiteUserChecklist->assignedByUser;
                    $checklistListing[$iterator]['assigned_user_name'] = $assignedByUser['first_name'].' '.$assignedByUser['last_name'];
                }
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
            $projectSiteUserCheckpoints = ProjectSiteUserCheckpoint::where('project_site_user_checklist_assignment_id',$request['project_site_user_checklist_assignment_id'])->orderBy('id','asc')
                                            ->get();
            $iterator = 0;
            $checkPointListing = array();
            foreach($projectSiteUserCheckpoints as $key => $projectSiteUserCheckpoint){
                $checkPointListing[$iterator]['project_site_user_checkpoint_id'] = $projectSiteUserCheckpoint['id'];
                $projectSiteChecklistCheckpoint = $projectSiteUserCheckpoint->projectSiteChecklistCheckpoint;
                $checkPointListing[$iterator]['project_site_user_checkpoint_description'] = $projectSiteChecklistCheckpoint->description;
                $checkPointListing[$iterator]['project_site_user_checkpoint_is_remark_required'] = $projectSiteChecklistCheckpoint->is_remark_required;
                $checkPointListing[$iterator]['project_site_user_checkpoint_is_ok'] = $projectSiteUserCheckpoint['is_ok'];
                $checkPointListing[$iterator]['project_site_user_checkpoint_images'] = array();
                $projectSiteChecklistCheckpointImages = $projectSiteChecklistCheckpoint->projectSiteChecklistCheckpointImages;
                $jIterator = 0;
                foreach($projectSiteChecklistCheckpointImages as $key1 => $projectSiteChecklistCheckpointImage){
                    $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_id'] = $projectSiteChecklistCheckpointImage['id'];
                    $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_caption'] = $projectSiteChecklistCheckpointImage['caption'];
                    $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_checklist_checkpoint_image_is_required'] = $projectSiteChecklistCheckpointImage['is_required'];
                    if($projectSiteUserCheckpoint['is_ok'] != null){
                        $imageUrl = ProjectSiteUserCheckpointImage::where('project_site_user_checkpoint_id',$projectSiteUserCheckpoint['id'])->where('project_site_checklist_checkpoint_image_id',$projectSiteChecklistCheckpointImage['id'])->first();
                        if(count($imageUrl) > 0){
                            $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_user_checkpoint_image_id'] = $imageUrl['id'];
                            $checkPointListing[$iterator]['project_site_user_checkpoint_images'][$jIterator]['project_site_user_checkpoint_image_url'] = $imageUrl['image'];
                        }
                    }
                    $jIterator++;
                }
                $iterator++;
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
}