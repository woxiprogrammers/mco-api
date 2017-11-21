<?php
    /**
     * Created by Harsha.
     * User: harsha
     * Date: 21/11/17
     * Time: 1:42 PM
     */
namespace App\Http\Controllers\Checklist;
use App\ChecklistCategory;
use App\ProjectSiteChecklist;
use App\ProjectSiteChecklistCheckpoint;
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
            $subCategoryIds = ProjectSiteChecklistCheckpoint::join('project_site_checklists','project_site_checklist_checkpoints.project_site_checklist_id','=','project_site_checklists.id')
                                                            ->where('project_site_checklists.project_site_id',$request['project_site_id'])
                                                            ->distinct('project_site_checklist_checkpoints.checklist_category_id')
                                                            ->pluck('project_site_checklist_checkpoints.checklist_category_id');
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
}